# Architecture decisions

## ADR-001: Laravel is the application control plane

Laravel owns authentication, tenancy, meetings, provider integrations, artifacts, queues, audit records and workflow state. Python remains focused on media and AI workloads.

## ADR-002: Post-meeting ingestion precedes live bots

The MVP imports provider artifacts or manual uploads. This validates the transcript, evidence and workflow domains without taking on real-time media transport, bot admission and platform-specific media SDK complexity.

## ADR-003: Preserve originals and version derivatives

Original recordings and provider transcripts are immutable. Every normalized or corrected transcript is a new version linked to its source artifact.

## ADR-004: Provider adapters are replaceable

Teams, Zoom and Google Meet integrations implement one application-facing contract. Provider payloads remain in provider event records and do not leak into the normalized meeting domain.

## ADR-005: The Python worker is stateless

The worker downloads an artifact from a signed URL, returns normalized output and does not write directly to the application database. Laravel remains the source of truth and controls transaction boundaries.
