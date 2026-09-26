from __future__ import annotations
from abc import ABC, abstractmethod
from pathlib import Path
import httpx
from .schemas import Segment
from .settings import settings

class TranscriptionBackend(ABC):
    @abstractmethod
    def transcribe(self, path: Path, language: str | None) -> tuple[list[Segment], str, list[str]]:
        raise NotImplementedError

class MockBackend(TranscriptionBackend):
    def transcribe(self, path: Path, language: str | None) -> tuple[list[Segment], str, list[str]]:
        segment = Segment(
            start_ms=0,
            end_ms=7000,
            speaker='Speaker 1',
            text=f'Mock transcription for {path.name}. Configure TRANSCRIPTION_BACKEND=openai for real audio transcription.',
            confidence=1.0,
            source_reference='mock:1',
        )
        return [segment], 'mock-transcriber', ['The audio/video artifact was processed by the mock backend.']

class OpenAIBackend(TranscriptionBackend):
    endpoint = 'https://api.openai.com/v1/audio/transcriptions'

    def transcribe(self, path: Path, language: str | None) -> tuple[list[Segment], str, list[str]]:
        if not settings.openai_api_key:
            raise RuntimeError('OPENAI_API_KEY is required when TRANSCRIPTION_BACKEND=openai.')
        with path.open('rb') as file_handle:
            files = {'file': (path.name, file_handle, 'application/octet-stream')}
            data = {
                'model': settings.openai_transcription_model,
                'response_format': 'diarized_json',
                'chunking_strategy': 'auto',
            }
            if language:
                data['language'] = language
            response = httpx.post(
                self.endpoint,
                headers={'Authorization': f'Bearer {settings.openai_api_key}'},
                files=files,
                data=data,
                timeout=840,
            )
        response.raise_for_status()
        payload = response.json()
        source_segments = payload.get('segments') or []
        if not source_segments and payload.get('text'):
            source_segments = [{'start': 0, 'end': max(1, len(payload['text'].split()) / 2.5), 'text': payload['text'], 'speaker': 'Speaker 1'}]
        segments = [
            Segment(
                start_ms=int(float(item.get('start', 0)) * 1000),
                end_ms=int(float(item.get('end', 0)) * 1000),
                speaker=item.get('speaker') or 'Speaker 1',
                text=str(item.get('text', '')).strip(),
                confidence=item.get('confidence'),
                source_reference=f'openai:{index}',
                metadata={'raw_id': item.get('id')},
            )
            for index, item in enumerate(source_segments, start=1)
            if str(item.get('text', '')).strip()
        ]
        return segments, settings.openai_transcription_model, ['Diarization labels identify voices; map them to meeting participants before treating them as confirmed identities.']

def get_backend() -> TranscriptionBackend:
    if settings.transcription_backend.lower() == 'openai':
        return OpenAIBackend()
    return MockBackend()
