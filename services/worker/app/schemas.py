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

class SummaryTopic(BaseModel):
    topic: str
    owner: str | None = None
    # Small models sometimes leave these out; defaults keep one missing field from failing the whole summary.
    summary: str = ''
    outcome: str = 'No decision'

class SummaryActionItem(BaseModel):
    owner: str | None = None          # was required: a missing owner used to fail the whole summary
    action: str
    context: str | None = None
    when: str | None = None           # the deadline as spoken, e.g. "by Friday"
    due_date: str | None = None       # NEW: the deadline resolved to YYYY-MM-DD, or None
    priority: str | None = None

class SummarizeRequest(BaseModel):
    meeting_id: str
    title: str
    date: str | None = None
    attendees: list[str] = []
    transcript_text: str

class SummarizeResponse(BaseModel):
    # Every section defaults to empty so a model that omits one key still produces a usable summary.
    executive_summary: str = ''
    quick_summary: list[str] = Field(default_factory=list)
    decisions: list[str] = Field(default_factory=list)
    topics: list[SummaryTopic] = Field(default_factory=list)
    action_items: list[SummaryActionItem] = Field(default_factory=list)
    risks: list[str] = Field(default_factory=list)
    dependencies: list[str] = Field(default_factory=list)
    unknowns: list[str] = Field(default_factory=list)
