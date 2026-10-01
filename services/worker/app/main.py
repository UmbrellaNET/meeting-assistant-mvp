from __future__ import annotations

import hmac
import json
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


async def verify_internal_secret(x_internal_secret: str = Header(default='')) -> None:
    if not hmac.compare_digest(x_internal_secret, settings.internal_shared_secret):
        raise HTTPException(status_code=401, detail='Invalid internal secret.')


@app.get('/health')
def health() -> dict:
    return {'status': 'ok', 'backend': settings.transcription_backend}


@app.post('/v1/transcribe', response_model=TranscriptionResponse, dependencies=[Depends(verify_internal_secret)])
async def transcribe(request: TranscriptionRequest) -> TranscriptionResponse:
    suffix = Path(request.filename or urlparse(str(request.source_url)).path).suffix.lower() or '.bin'
    with tempfile.TemporaryDirectory(prefix='meeting-artifact-') as tmp:
        path = Path(tmp) / f'source{suffix}'
        total = 0
        async with httpx.AsyncClient(follow_redirects=True, timeout=840) as client:
            async with client.stream('GET', str(request.source_url)) as response:
                response.raise_for_status()
                with path.open('wb') as handle:
                    async for chunk in response.aiter_bytes():
                        total += len(chunk)
                        if total > settings.max_download_bytes:
                            raise HTTPException(status_code=413, detail='Artifact exceeds worker download limit.')
                        handle.write(chunk)

        warnings: list[str] = []
        provider = 'source-parser'
        model: str | None = None
        if suffix == '.vtt':
            segments = parse_vtt(path)
        elif suffix in {'.txt', '.md'}:
            segments = parse_plain_text(path)
        else:
            segments, model, warnings = get_backend().transcribe(path, request.language)
            provider = settings.transcription_backend

        if not segments:
            raise HTTPException(status_code=422, detail='No transcript segments were produced.')
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


@app.post('/v1/summarize', response_model=SummarizeResponse, dependencies=[Depends(verify_internal_secret)])
async def summarize(request: SummarizeRequest) -> SummarizeResponse:
    client = get_gemini_client()
    user_prompt = (
        f"Meeting title: {request.title}\n"
        f"Date: {request.date or 'unknown'}\n"
        f"Attendees: {', '.join(request.attendees) if request.attendees else 'unknown'}\n\n"
        f"Transcript:\n{request.transcript_text}"
    )

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
        raise HTTPException(status_code=502, detail=f'Gemini request failed: {exc}')

    try:
        text = (response.text or '').strip()
        # Defensive: strip markdown fences if the model adds them
        if text.startswith('```'):
            text = text.strip('`').removeprefix('json').strip()
        data = json.loads(text)
        return SummarizeResponse(**data)
    except Exception as exc:
        raise HTTPException(status_code=502, detail=f'Failed to parse summary: {exc}')