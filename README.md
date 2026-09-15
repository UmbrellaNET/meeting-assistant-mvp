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
- Docker Compose for local development (when Docker Desktop works)
- ServBay native setup on Windows when Docker/WSL is unavailable

## Repository layout

```text
apps/api/              Laravel API, jobs, domain models and migrations
apps/web/              Next.js dashboard and transcript review UI
services/worker/       Python transcription and normalization service
docs/                  Architecture, sequence diagrams and OpenAPI starter
samples/               Sample transcript file for smoke testing
compose.yaml           Local development environment (Docker)
dev.cmd / dev-stop.cmd Daily start/stop on Windows (ServBay)
scripts/dev.ps1        Implementation of .\dev
```

## Start the app locally (daily)

On Windows without Docker Desktop, use ServBay. After the [first-time ServBay setup](docs/servbay-local-setup.md), start everything from the **repo root** in PowerShell:

```powershell
.\dev
```

That is the command to run each time you want to develop locally. It:

1. Checks that **PostgreSQL** is already running in ServBay (port `5432`). If it is not, start Postgres in ServBay and run `.\dev` again.
2. Starts **Redis** (port `6379`) if it is down.
3. Starts **MinIO** (ports `9000` / `9001`) if it is down, and ensures the `meeting-artifacts` bucket exists.
4. Opens four PowerShell windows for:
   - Laravel API (`php artisan serve` on `:8000`)
   - Laravel queue worker (`php artisan queue:work`)
   - Python AI worker (`uvicorn` on `:8100`)
   - Next.js web UI (`npm run dev` on `:3000`)
5. Waits until the API and web are responding, then opens **http://127.0.0.1:3000**.

If a service is already listening on its port, `.\dev` leaves it running and only starts what is missing. It always starts a new queue-worker window (the queue has no port to check).

### Stop

```powershell
.\dev-stop
```

This stops the API, queue worker, AI worker, and web UI. PostgreSQL, Redis, and MinIO are left running.

### URLs

| Service | URL |
|---------|-----|
| Web UI | http://127.0.0.1:3000 |
| API health | http://127.0.0.1:8000/up |
| API | http://127.0.0.1:8000/api |
| AI worker | http://127.0.0.1:8100/health |
| MinIO console | http://127.0.0.1:9001 (`minioadmin` / `minioadmin123`) |

### Sign in

Seeded accounts (password for both is `password`):

| Email | Role |
|-------|------|
| `meetings@umbrellanet.com` | Super admin |
| `user@umbrellanet.com` | Standard user |

The login form may still prefill `developer@example.com`. Use `meetings@umbrellanet.com` instead.

### Troubleshooting

| Symptom | Fix |
|---------|-----|
| `Postgres is not listening on 5432` | Start PostgreSQL in ServBay, then run `.\dev` again |
| Web window errors on `NEXT_PUBLIC_API_URL` | You are on an old `.\dev` script; pull/save the current `scripts/dev.ps1` and run `.\dev` again |
| Port already in use / duplicate windows | Run `.\dev-stop`, then `.\dev` |
| Web cannot reach the API | Confirm the API window is serving on `:8000`. `.\dev` sets `NEXT_PUBLIC_API_URL=http://localhost:8000/api` |
| Queue jobs stuck | Confirm the **Queue** window is running and the AI worker is healthy on `:8100` |

First-time install (PHP, Node, Python, database, env files, migrate/seed) is documented in **[docs/servbay-local-setup.md](docs/servbay-local-setup.md)**.

## First-time setup (Docker Compose)

Use this path when Docker Desktop is healthy:

```bash
cp .env.example .env

docker compose build
docker compose up -d postgres redis minio minio-init worker

docker compose run --rm api php artisan key:generate --show
# Copy the generated key into APP_KEY in .env

docker compose up -d
docker compose exec api php artisan migrate --seed
```

Daily Docker start after that is `make up` (or `docker compose up -d`). Daily Docker stop is `make down`.

## First smoke test

1. Log in at http://127.0.0.1:3000 with `meetings@umbrellanet.com` / `password`.
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
