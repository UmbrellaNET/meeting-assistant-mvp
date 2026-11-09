from __future__ import annotations

import asyncio
import hmac
import json
import logging
import os
import tempfile
from pathlib import Path
from urllib.parse import urlparse

import httpx
from fastapi import Depends, FastAPI, Header, HTTPException
from google import genai
from google.genai import types

from .backends import get_backend
from .media import duration_ms
from .parsers import parse_plain_text, parse_vtt
from .schemas import (
    SummarizeRequest,
    SummarizeResponse,
    TranscriptionRequest,
    TranscriptionResponse,
)
from .settings import settings

app = FastAPI(title='Meeting Intelligence AI Worker', version='0.1.0')

# "uvicorn.error" is configured by uvicorn, so these lines show up in Render's logs.
logger = logging.getLogger('uvicorn.error')


async def verify_internal_secret(x_internal_secret: str = Header(default='')) -> None:
    if not hmac.compare_digest(x_internal_secret, settings.internal_shared_secret):
        raise HTTPException(status_code=401, detail='Invalid internal secret.')


@app.get('/health')
def health() -> dict:
    return {
        'status': 'ok',
        'backend': settings.transcription_backend,
        'summary_backend': _summary_backend(),
        'summary_fallback': os.getenv('SUMMARY_FALLBACK', '').strip().lower() or None,
    }


@app.post('/v1/transcribe', response_model=TranscriptionResponse, dependencies=[Depends(verify_internal_secret)])
async def transcribe(request: TranscriptionRequest) -> TranscriptionResponse:
    source_host = urlparse(str(request.source_url)).netloc
    logger.info(
        'transcribe received meeting=%s artifact=%s file=%s type=%s source_host=%s',
        getattr(request, 'meeting_id', None),
        getattr(request, 'artifact_id', None),
        request.filename,
        getattr(request, 'artifact_type', None),
        source_host,
    )

    suffix = Path(request.filename or urlparse(str(request.source_url)).path).suffix.lower() or '.bin'
    with tempfile.TemporaryDirectory(prefix='meeting-artifact-') as tmp:
        path = Path(tmp) / f'source{suffix}'
        total = 0
        try:
            async with httpx.AsyncClient(follow_redirects=True, timeout=840) as client:
                async with client.stream('GET', str(request.source_url)) as response:
                    response.raise_for_status()
                    with path.open('wb') as handle:
                        async for chunk in response.aiter_bytes():
                            total += len(chunk)
                            if total > settings.max_download_bytes:
                                raise HTTPException(status_code=413, detail='Artifact exceeds worker download limit.')
                            handle.write(chunk)
        except HTTPException:
            raise
        except httpx.HTTPStatusError as exc:
            status = exc.response.status_code
            logger.warning('source download failed: HTTP %s from %s', status, source_host)
            # Rate limits / gateway errors on the source are temporary: report 503 so the API retries.
            code = 503 if status in (429, 502, 503, 504) else 502
            raise HTTPException(
                status_code=code,
                detail=f'Could not download source file from {source_host}: HTTP {status}',
                headers={'Retry-After': '60'} if code == 503 else None,
            ) from exc
        except httpx.HTTPError as exc:
            logger.warning('source download error from %s: %s', source_host, exc)
            raise HTTPException(
                status_code=502,
                detail=f'Could not download source file from {source_host}: {exc.__class__.__name__}: {exc}',
            ) from exc

        warnings: list[str] = []
        provider = 'source-parser'
        model: str | None = None
        if suffix == '.vtt':
            segments = parse_vtt(path)
        elif suffix in {'.txt', '.md'}:
            segments = parse_plain_text(path)
        else:
            # Run the blocking transcription in a thread so /health and other requests keep responding.
            segments, model, warnings = await asyncio.to_thread(get_backend().transcribe, path, request.language)
            provider = settings.transcription_backend

        if not segments:
            raise HTTPException(status_code=422, detail='No transcript segments were produced.')
        logger.info('transcribe done: %d segments via %s', len(segments), provider)
        return TranscriptionResponse(
            provider=provider,
            model=model,
            language=request.language or 'en',
            duration_ms=duration_ms(path),
            segments=segments,
            warnings=warnings,
        )


# ---------------------------------------------------------------------------
# Meeting summaries (Gemini 2.5 Flash)
# ---------------------------------------------------------------------------

_gemini_client: genai.Client | None = None


def get_gemini_client() -> genai.Client:
    global _gemini_client
    if _gemini_client is None:
        if not settings.gemini_api_key:
            raise HTTPException(status_code=500, detail='Gemini API key not configured.')
        _gemini_client = genai.Client(api_key=settings.gemini_api_key)
    return _gemini_client


SUMMARY_SYSTEM_PROMPT = """You are an assistant that produces structured meeting notes from a transcript.
Return ONLY valid JSON matching this exact shape, no prose outside the JSON:

{
  "executive_summary": "2-4 sentence paragraph overview of the meeting",
  "quick_summary": ["short bullet", "short bullet", ...],
  "decisions": ["decision made", ...],
  "topics": [
    {"topic": "Topic name", "owner": "Person or null", "summary": "paragraph", "outcome": "Decision|Deferred|No decision"}
  ],
  "action_items": [
    {"owner": "Name", "action": "what to do", "context": "why", "when": "deadline or timeframe", "priority": "High|Medium|Low"}
  ],
  "risks": ["risk description", ...],
  "dependencies": ["dependency description", ...],
  "unknowns": ["open question", ...]
}

Base everything strictly on the transcript content. If a section has nothing to report, return an empty array for it (never omit the key)."""


OPENAI_STYLE_BACKENDS = {'openai', 'openai_compatible', 'vllm', 'hf', 'huggingface', 'open'}


def _summary_backend() -> str:
    """'gemini' (default) or an OpenAI-compatible server such as vLLM / HF Inference Endpoints."""
    return os.getenv('SUMMARY_BACKEND', 'gemini').strip().lower()


def _clean_model_text(text: str) -> str:
    """Reduce a model reply to a bare JSON object string."""
    text = (text or '').strip()
    # Gemma 4 can emit an (empty) thinking block: <|channel>thought\n...<channel|>final answer
    if '<channel|>' in text:
        text = text.rsplit('<channel|>', 1)[1].strip()
    # Defensive: strip markdown fences if the model adds them
    if text.startswith('```'):
        text = text.strip('`').removeprefix('json').strip()
    start, end = text.find('{'), text.rfind('}')
    if start != -1 and end > start:
        text = text[start:end + 1]
    return text


def _retryable_status(status: int) -> bool:
    return status in (429, 502, 503, 504)


async def _summarize_gemini(user_prompt: str) -> str:
    client = get_gemini_client()
    try:
        response = await client.aio.models.generate_content(
            model=settings.gemini_model,
            contents=user_prompt,
            config=types.GenerateContentConfig(
                system_instruction=SUMMARY_SYSTEM_PROMPT,
                response_mime_type='application/json',
                temperature=0.2,
            ),
        )
    except Exception as exc:
        message = str(exc)
        logger.warning('Gemini request failed: %s', message[:300])
        # Gemini quota / overload errors are temporary: report 503 so the API retries later.
        if '429' in message or 'RESOURCE_EXHAUSTED' in message or '503' in message or 'UNAVAILABLE' in message:
            raise HTTPException(
                status_code=503,
                detail=f'Gemini is rate limited or overloaded: {message[:300]}',
                headers={'Retry-After': '60'},
            )
        raise HTTPException(status_code=502, detail=f'Gemini request failed: {exc}')
    return response.text or ''


async def _summarize_openai_compatible(user_prompt: str) -> str:
    """Call any OpenAI-compatible chat endpoint (vLLM, HF Inference Endpoints, Together, RunPod, ...).

    Env vars: LLM_BASE_URL (include /v1), LLM_MODEL, LLM_API_KEY (optional), LLM_TEMPERATURE (optional).
    """
    base_url = os.getenv('LLM_BASE_URL', '').strip().rstrip('/')
    model = os.getenv('LLM_MODEL', '').strip()
    if not base_url or not model:
        raise HTTPException(status_code=500, detail='LLM_BASE_URL and LLM_MODEL must be set when SUMMARY_BACKEND is not gemini.')

    headers = {'Content-Type': 'application/json'}
    api_key = os.getenv('LLM_API_KEY', '').strip()
    if api_key:
        headers['Authorization'] = f'Bearer {api_key}'

    payload: dict = {
        'model': model,
        'messages': [
            {'role': 'system', 'content': SUMMARY_SYSTEM_PROMPT},
            {'role': 'user', 'content': user_prompt},
        ],
        'temperature': float(os.getenv('LLM_TEMPERATURE', '0.3')),
        'top_p': 0.95,
        'max_tokens': int(os.getenv('LLM_MAX_TOKENS', '4096')),
        'response_format': {'type': 'json_object'},
    }
    url = f'{base_url}/chat/completions'

    try:
        # GPU endpoints can take minutes to cold start, so use a long timeout.
        async with httpx.AsyncClient(timeout=600) as client:
            resp = await client.post(url, json=payload, headers=headers)
            if resp.status_code == 400 and 'response_format' in payload:
                # Some servers do not support response_format; retry without it.
                payload.pop('response_format')
                resp = await client.post(url, json=payload, headers=headers)
    except httpx.HTTPError as exc:
        logger.warning('LLM request error: %s', exc)
        raise HTTPException(status_code=503, detail=f'LLM endpoint unreachable: {exc.__class__.__name__}: {exc}', headers={'Retry-After': '60'})

    if resp.status_code >= 400:
        logger.warning('LLM endpoint returned HTTP %s: %s', resp.status_code, resp.text[:300])
        code = 503 if _retryable_status(resp.status_code) else 502
        raise HTTPException(
            status_code=code,
            detail=f'LLM endpoint failed: HTTP {resp.status_code} {resp.text[:300]}',
            headers={'Retry-After': '60'} if code == 503 else None,
        )

    try:
        return resp.json()['choices'][0]['message']['content'] or ''
    except Exception as exc:
        raise HTTPException(status_code=502, detail=f'Unexpected LLM response format: {exc}')


_gradio_client = None


def _get_gradio_client():
    global _gradio_client
    if _gradio_client is None:
        try:
            from gradio_client import Client
        except ImportError as exc:
            raise HTTPException(
                status_code=500,
                detail='gradio_client is not installed on the worker (add gradio_client to its requirements).',
            ) from exc
        url = os.getenv('GRADIO_SPACE_URL', 'https://tarirozw-gemma4.hf.space').strip()
        token = os.getenv('HF_TOKEN', '').strip()
        try:
            _gradio_client = Client(url, hf_token=token) if token else Client(url)
        except TypeError:
            # newer gradio_client versions renamed the argument
            _gradio_client = Client(url, token=token) if token else Client(url)
    return _gradio_client


def _gradio_result_to_text(result) -> str:
    if isinstance(result, str):
        return result
    if isinstance(result, dict):
        return str(result.get('content') or result.get('text') or '')
    if isinstance(result, (list, tuple)):
        for item in reversed(result):
            text = _gradio_result_to_text(item)
            if text:
                return text
        return ''
    return str(result or '')


def _gradio_predict(prompt: str) -> str:
    """Blocking call to a Hugging Face Gradio Space (run it in a thread)."""
    client = _get_gradio_client()
    api_name = os.getenv('GRADIO_API_NAME', '/chat')
    prompt_param = os.getenv('GRADIO_PROMPT_PARAM', 'message')
    extra = json.loads(os.getenv('GRADIO_EXTRA_KWARGS', '') or '{}')
    job = client.submit(api_name=api_name, **{prompt_param: prompt}, **extra)
    return _gradio_result_to_text(job.result(timeout=600))


async def _summarize_gradio(user_prompt: str) -> str:
    """Summaries from a Gradio Space (for example a small open model such as Gemma 4 E4B).

    Env vars: GRADIO_SPACE_URL, GRADIO_API_NAME (default /chat), GRADIO_PROMPT_PARAM (default message),
    GRADIO_EXTRA_KWARGS (JSON, e.g. {"max_new_tokens": 4096}), HF_TOKEN (private Spaces / higher quota).
    """
    # Small models with a limited output budget need a shorter target, or the JSON gets cut off.
    concise = (
        "\n\nBe concise so the whole JSON fits in a short reply: at most 6 items in quick_summary, "
        "at most 8 action_items, at most 5 topics, and keep every summary field under 40 words."
    )
    prompt = f"{SUMMARY_SYSTEM_PROMPT}{concise}\n\n---\n\n{user_prompt}"
    try:
        return await asyncio.to_thread(_gradio_predict, prompt)
    except HTTPException:
        raise
    except Exception as exc:
        message = str(exc)
        logger.warning('Gradio Space request failed: %s: %s', exc.__class__.__name__, message[:300])
        # A wrong endpoint or parameter name will not fix itself: do not retry.
        if 'api_name' in message or 'Cannot find a function' in message or 'unexpected keyword' in message:
            raise HTTPException(
                status_code=502,
                detail=f'Gradio endpoint/parameter mismatch (check GRADIO_API_NAME / GRADIO_PROMPT_PARAM): {message[:300]}',
            )
        # Sleeping Space, queue full or GPU quota: temporary, so ask the API to retry later.
        raise HTTPException(
            status_code=503,
            detail=f'Gradio Space request failed: {exc.__class__.__name__}: {message[:300]}',
            headers={'Retry-After': '90'},
        )


@app.post('/v1/summarize', response_model=SummarizeResponse, dependencies=[Depends(verify_internal_secret)])
async def summarize(request: SummarizeRequest) -> SummarizeResponse:
    user_prompt = (
        f"Meeting title: {request.title}\n"
        f"Date: {request.date or 'unknown'}\n"
        f"Attendees: {', '.join(request.attendees) if request.attendees else 'unknown'}\n\n"
        f"Transcript:\n{request.transcript_text}"
    )

    backend = _summary_backend()
    fallback = os.getenv('SUMMARY_FALLBACK', '').strip().lower()
    logger.info('summarize via %s (%d chars of transcript)', backend, len(request.transcript_text or ''))

    text = ''
    try:
        if backend in OPENAI_STYLE_BACKENDS:
            text = await _summarize_openai_compatible(user_prompt)
        elif backend == 'gradio':
            text = await _summarize_gradio(user_prompt)
            # Small models sometimes break the JSON format: retry once with a stricter reminder.
            try:
                json.loads(_clean_model_text(text))
            except Exception:
                text = await _summarize_gradio(user_prompt + '\n\nIMPORTANT: reply with ONLY the JSON object, nothing else.')
        else:
            text = await _summarize_gemini(user_prompt)
        return SummarizeResponse(**json.loads(_clean_model_text(text)))
    except Exception as exc:
        reason = exc.detail if isinstance(exc, HTTPException) else f'{exc.__class__.__name__}: {exc}'
        # Optional safety net: if the primary backend fails (quota, sleeping Space, bad JSON), use Gemini.
        if fallback == 'gemini' and backend != 'gemini':
            logger.warning('summary via %s failed (%s); falling back to Gemini', backend, str(reason)[:300])
            text = await _summarize_gemini(user_prompt)  # raises its own HTTPException on failure
            try:
                return SummarizeResponse(**json.loads(_clean_model_text(text)))
            except Exception as exc2:
                logger.warning('fallback summary parse failed: %s | tail=%r', exc2, (text or '')[-120:])
                raise HTTPException(status_code=502, detail=f'Failed to parse summary: {exc2}')
        if isinstance(exc, HTTPException):
            raise
        # Log the size and the end of the reply: a reply that stops mid-string means the output limit was hit.
        logger.warning('summary parse failed: %s | reply length=%d tail=%r', exc, len(text or ''), (text or '')[-120:])
        raise HTTPException(status_code=502, detail=f'Failed to parse summary: {exc}')
