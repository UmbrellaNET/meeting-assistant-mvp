from __future__ import annotations
import hmac
import tempfile
from pathlib import Path
from urllib.parse import urlparse
import httpx
from fastapi import Depends, FastAPI, Header, HTTPException
from .backends import get_backend
from .media import duration_ms
from .parsers import parse_plain_text, parse_vtt
from .schemas import TranscriptionRequest, TranscriptionResponse
from .settings import settings

app = FastAPI(title='Meeting Intelligence AI Worker', version='0.1.0')

async def verify_internal_secret(x_internal_secret: str = Header(default='')) -> None:
    if not hmac.compare_digest(x_internal_secret, settings.internal_shared_secret):
        raise HTTPException(status_code=401, detail='Invalid internal secret.')

@app.get('/health')
def health() -> dict:
    return {'status':'ok','backend':settings.transcription_backend}

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
