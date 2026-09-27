# AI Meeting Assistant MVP Starter

A development-ready monorepo for a post-meeting AI assistant. The starter focuses on the first vertical slice:

1. Register or import a meeting.
2. Upload a recording or transcript.
3. Store the original artifact in S3-compatible storage.
4. Queue post-meeting processing.
5. Transcribe or normalize the artifact through a Python worker.
6. Persist timestamped speaker segments.
7. Review the transcript and correct speaker identities.
8. Retain evidence links back to the source artifact.

## Stack

- Laravel 13 API and queue orchestration
- Next.js 16 App Router UI
- Python FastAPI media/transcription service
- PostgreSQL
- Redis queues and cache
- MinIO for local S3-compatible object storage
- Docker Compose for local development (preferred when Docker works)
- ServBay native setup on Windows when Docker/WSL is unavailable

## Repository layout

```text
apps/api/              Laravel API, jobs, domain models and migrations
apps/web/              Next.js dashboard and transcript review UI
services/worker/       Python transcription and normalization service
docs/                  Architecture, sequence diagrams and OpenAPI starter
samples/               Sample transcript file for smoke testing
compose.yaml            Local development environment
```

## Quick start (Docker Compose)

Preferred when Docker Desktop is healthy:

```bash
cp .env.example .env

docker compose build
docker compose up -d postgres redis minio minio-init worker

docker compose run --rm api php artisan key:generate --show
# Copy the generated key into APP_KEY in .env

docker compose up -d
docker compose exec api php artisan migrate --seed
```

### Quick start (ServBay / native Windows)

If Docker cannot start (common when WSL2 or firmware virtualization is disabled), use ServBay for PHP, Composer, Node, Python, Postgres, and Redis, plus a local MinIO binary.

Full step-by-step guide: **[docs/servbay-local-setup.md](docs/servbay-local-setup.md)**.

## URLs and demo account

Open:

- Web UI: http://localhost:3000
- API: http://localhost:8000/api
- Worker health: http://localhost:8100/health
- MinIO console: http://localhost:9001

Demo account created by the seeder:

```text
Email: developer@example.com
Password: password
```

## First smoke test

1. Log in with the seeded account.
2. Create a meeting.
3. Upload `samples/sample-meeting.vtt` as a transcript artifact.
4. Refresh the meeting page after the queue processes the file.
5. Review speaker segments and timestamps.

## MVP boundaries

Included:

- Manual recording/transcript upload
- Provider connector interfaces and webhook endpoint
- Asynchronous artifact processing
- Transcript versions and segments
- Speaker identity correction
- Evidence-link schema
- Audit-ready immutable original artifacts
- Mock and OpenAI transcription backend interfaces

Intentionally deferred:

- Live meeting bot participation
- Calendar synchronization
- Production OAuth flows for Teams, Zoom and Google
- Real-time captions
- Summary, decision, action, risk and opportunity extraction
- Full enterprise RBAC and data-retention administration

## Provider adapter strategy

Provider-specific code lives behind `MeetingProvider`. The included adapters expose the expected methods but deliberately throw a configuration exception until credentials and OAuth flows are added. This allows the core ingestion and processing pipeline to be developed and tested before introducing provider complexity.

## Security notes

The starter is a development baseline, not a production security certification. Before production deployment, add managed secrets, provider-specific webhook validation, malware scanning, object-lock/retention policies, tenant-aware authorization policies, rate limiting, full audit logging and environment-specific network controls.
