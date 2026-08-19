from pydantic import BaseModel, Field, HttpUrl

class TranscriptionRequest(BaseModel):
    meeting_id: str
    artifact_id: str
    artifact_type: str
    source_url: HttpUrl
    filename: str | None = None
    mime_type: str | None = None
    language: str | None = 'en'

class Segment(BaseModel):
    start_ms: int = Field(ge=0)
    end_ms: int = Field(ge=0)
    speaker: str
    text: str
    confidence: float | None = Field(default=None, ge=0, le=1)
    source_reference: str | None = None
    metadata: dict = Field(default_factory=dict)

class TranscriptionResponse(BaseModel):
    provider: str
    model: str | None = None
    language: str = 'en'
    duration_ms: int | None = None
    segments: list[Segment]
    warnings: list[str] = Field(default_factory=list)
