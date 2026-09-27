# Local setup with ServBay (Windows)

Use this guide when **Docker Compose is not available** — for example when Docker Desktop cannot start because WSL2 / Virtual Machine Platform / firmware virtualization is disabled.

ServBay supplies PHP, Composer, Node, Python, PostgreSQL, and Redis on the host. MinIO is started as a local binary. App processes (API, queue, worker, web) run natively in separate terminals.

## When to use which path

| Path | Use when |
|------|----------|
| [Docker Compose](../README.md#quick-start) | Docker Desktop engine is healthy |
| **This guide (ServBay)** | Docker/WSL cannot run; ServBay is installed |

## Prerequisites

Install and enable in ServBay:

- PHP **8.4** (with `pdo_pgsql`, `pgsql`, `redis`, `mbstring`, `curl`, `zip`, `intl`, `bcmath`, `dom`, `xml`)
- Composer
- Node.js **20+** (23 works; you may see an `EBADENGINE` warning from eslint)
- Python **3.13**
- PostgreSQL **16+**
- Redis

Confirm on PATH (ServBay usually adds `C:\ServBay\bin`):

```powershell
php -v
composer -V
node -v
npm -v
python --version
psql --version
```

Default Postgres superuser password on ServBay is typically `ServBay.dev` (confirm in the ServBay UI if login fails).

### Optional: ffmpeg

VTT / plain-text uploads work **without** ffmpeg. Audio/video duration probing uses `ffprobe`; if it is missing, duration is left empty and processing continues. Install ffmpeg only if you need accurate media duration locally.

## 1. Start infrastructure

From an elevated or normal PowerShell as needed.

### PostgreSQL

Start PostgreSQL from the ServBay app, or:

```powershell
& "C:\ServBay\packages\postgresql\current\bin\pg_ctl.exe" start `
  -D "C:\ServBay\db\postgresql\16" `
  -l "C:\ServBay\logs\postgresql\16\server.log"
```

Create the app role and database (password for `postgres` is often `ServBay.dev`):

```powershell
$env:PGPASSWORD = "ServBay.dev"
$psql = "C:\ServBay\packages\postgresql\current\bin\psql.exe"

& $psql -U postgres -h 127.0.0.1 -c "CREATE ROLE meeting_user LOGIN PASSWORD 'meeting_password';"
& $psql -U postgres -h 127.0.0.1 -c "CREATE DATABASE meeting_assistant OWNER meeting_user;"
& $psql -U postgres -h 127.0.0.1 -d meeting_assistant -c "GRANT ALL ON SCHEMA public TO meeting_user; ALTER SCHEMA public OWNER TO meeting_user;"
```

Skip `CREATE ROLE` / `CREATE DATABASE` if they already exist. Verify:

```powershell
$env:PGPASSWORD = "meeting_password"
& $psql -U meeting_user -h 127.0.0.1 -d meeting_assistant -c "SELECT current_database(), current_user;"
```

### Redis

ServBay’s Redis build is MSYS-based and mishandles some Windows absolute paths. Prefer a small local config:

```powershell
New-Item -ItemType Directory -Force -Path "C:\ServBay\db\redis","C:\ServBay\logs\redis" | Out-Null

@"
bind 127.0.0.1
port 6379
protected-mode yes
daemonize no
dir C:/ServBay/db/redis
dbfilename dump.rdb
logfile C:/ServBay/logs/redis/redis-local.log
"@ | Set-Content "C:\ServBay\packages\redis\redis-local.conf" -Encoding ascii

Start-Process -FilePath "C:\ServBay\packages\redis\redis-server.exe" `
  -ArgumentList "redis-local.conf" `
  -WorkingDirectory "C:\ServBay\packages\redis" `
  -WindowStyle Hidden

& "C:\ServBay\packages\redis\redis-cli.exe" ping
# Expect: PONG
```

### MinIO

If MinIO is not already provided by ServBay, download the Windows binaries once:

```powershell
$minioDir = "C:\ServBay\db\minio"
New-Item -ItemType Directory -Force -Path $minioDir,"$minioDir\data" | Out-Null

Invoke-WebRequest "https://dl.min.io/server/minio/release/windows-amd64/minio.exe" `
  -OutFile "$minioDir\minio.exe" -UseBasicParsing
Invoke-WebRequest "https://dl.min.io/client/mc/release/windows-amd64/mc.exe" `
  -OutFile "$minioDir\mc.exe" -UseBasicParsing
```

Start MinIO and create the bucket:

```powershell
$env:MINIO_ROOT_USER = "minioadmin"
$env:MINIO_ROOT_PASSWORD = "minioadmin123"

Start-Process -FilePath "C:\ServBay\db\minio\minio.exe" `
  -ArgumentList "server","C:\ServBay\db\minio\data","--address",":9000","--console-address",":9001" `
  -WorkingDirectory "C:\ServBay\db\minio" `
  -WindowStyle Hidden

Start-Sleep 3
$mc = "C:\ServBay\db\minio\mc.exe"
& $mc alias set local http://127.0.0.1:9000 minioadmin minioadmin123
& $mc mb --ignore-existing local/meeting-artifacts
```

Console: http://localhost:9001 (`minioadmin` / `minioadmin123`).

## 2. Clone / open the repo and install dependencies

```powershell
cd path\to\meeting-assistant-mvp-starter

Copy-Item .env.example .env

cd apps\api
composer install
cd ..\..\apps\web
npm install
cd ..\..\services\worker
python -m pip install -r requirements.txt
cd ..\..
```

## 3. Environment files (localhost hosts)

Docker Compose uses service hostnames (`postgres`, `redis`, `minio`, `worker`). On ServBay everything listens on `127.0.0.1`.

### Root `.env`

Keep ports and secrets from `.env.example`. After generating the key (next step), set `APP_KEY`. Ensure:

```env
S3_BUCKET=meeting-artifacts
```

### `apps/api/.env`

Create from the template below (do not use Docker hostnames):

```env
APP_NAME="Meeting Intelligence MVP"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:3000

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=meeting_assistant
DB_USERNAME=meeting_user
DB_PASSWORD=meeting_password

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=database
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=minioadmin123
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=meeting-artifacts
AWS_ENDPOINT=http://127.0.0.1:9000
AWS_PUBLIC_ENDPOINT=http://127.0.0.1:9000
AWS_USE_PATH_STYLE_ENDPOINT=true

AI_WORKER_URL=http://127.0.0.1:8100
INTERNAL_SHARED_SECRET=change-this-internal-secret
WEBHOOK_SHARED_SECRET=change-this-webhook-secret
```

### `services/worker/.env`

```env
PORT=8100
TRANSCRIPTION_BACKEND=mock
INTERNAL_SHARED_SECRET=change-this-internal-secret
```

`INTERNAL_SHARED_SECRET` must match the API value.

### Generate `APP_KEY`

```powershell
cd apps\api
php artisan key:generate --show
```

Paste the printed value into both `apps/api/.env` and the root `.env` as `APP_KEY=...`.

## 4. Migrate and seed

```powershell
cd apps\api
php artisan migrate --seed --force
```

Demo account:

```text
Email: developer@example.com
Password: password
```

## 5. Run the four app processes

Open **four** PowerShell windows from the repo root.

**Terminal A — API**

```powershell
cd apps\api
php artisan serve --host=127.0.0.1 --port=8000
```

**Terminal B — queue worker**

```powershell
cd apps\api
php artisan queue:work --queue=default,meetings --tries=3 --timeout=900
```

**Terminal C — AI worker**

```powershell
cd services\worker
python -m uvicorn app.main:app --host 127.0.0.1 --port 8100
```

**Terminal D — web UI**

```powershell
cd apps\web
$env:NEXT_PUBLIC_API_URL = "http://localhost:8000/api"
npm run dev -- --port 3000 --hostname 127.0.0.1
```

### Health checks

| Service | URL |
|---------|-----|
| Web | http://localhost:3000 |
| API | http://localhost:8000/up |
| Worker | http://localhost:8100/health |
| MinIO API | http://localhost:9000/minio/health/live |
| MinIO console | http://localhost:9001 |

## 6. Smoke test

1. Open http://localhost:3000 and sign in with the demo account.
2. Create a meeting.
3. Upload [`samples/sample-meeting.vtt`](../samples/sample-meeting.vtt).
   - UI should send artifact type `uploaded_transcript` (valid API values: `audio`, `video`, `provider_transcript`, `uploaded_transcript`).
4. Wait for the queue + worker (a few seconds), then refresh the meeting.
5. Confirm speaker segments and timestamps appear; meeting status should become `ready`.

API-only check:

```powershell
# Login
$login = Invoke-RestMethod http://127.0.0.1:8000/api/auth/login -Method Post `
  -ContentType application/json `
  -Body '{"email":"developer@example.com","password":"password"}'
$token = $login.token

# Create meeting
$meeting = Invoke-RestMethod http://127.0.0.1:8000/api/meetings -Method Post `
  -Headers @{ Authorization = "Bearer $token" } `
  -ContentType application/json `
  -Body '{"title":"ServBay smoke test"}'

# Upload sample VTT
curl.exe -s -X POST "http://127.0.0.1:8000/api/meetings/$($meeting.id)/artifacts" `
  -H "Authorization: Bearer $token" -H "Accept: application/json" `
  -F "file=@samples/sample-meeting.vtt;type=text/vtt" `
  -F "artifact_type=uploaded_transcript"
```

## Port map

| Port | Service |
|------|---------|
| 3000 | Next.js web |
| 8000 | Laravel API |
| 8100 | Python worker |
| 5432 | PostgreSQL |
| 6379 | Redis |
| 9000 | MinIO S3 API |
| 9001 | MinIO console |

## Troubleshooting

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| Docker `pipe/dockerDesktopLinuxEngine` errors | No WSL / virtualization | Use this ServBay guide |
| `password authentication failed for user "meeting_user"` | DB not created or wrong password | Re-run role/DB creation; app password is `meeting_password` |
| Redis `can't open config file` / path under `/cygdrive` | MSYS path handling | Use `redis-local.conf` under `C:\ServBay\packages\redis` as above |
| MinIO connection refused | MinIO not started | Start `minio.exe`; check port 9000 |
| API `The selected artifact type is invalid` | Wrong `artifact_type` | Use `uploaded_transcript` for VTT/text |
| Worker `FileNotFoundError: ffprobe` | ffmpeg not installed | VTT/text should still work after current `media.py` (missing ffprobe returns `None`). Install ffmpeg for media duration |
| Queue stuck / `AI worker failed` | Worker down or secret mismatch | Ensure worker is on `:8100` and `INTERNAL_SHARED_SECRET` matches API |
| Web cannot reach API | Wrong public API URL | Set `NEXT_PUBLIC_API_URL=http://localhost:8000/api` when starting Next |
| Port already in use | Another process bound | Change ports in env / artisan / uvicorn / next, or stop the conflicting process |

## Day-to-day restart checklist

1. Start Postgres (ServBay) + Redis + MinIO if not already running.
2. Start the four app terminals (API, queue, AI worker, web).
3. Open http://localhost:3000.

You do not need to re-run `composer install` / `npm install` / `pip install` or migrations unless dependencies or schema changed.
