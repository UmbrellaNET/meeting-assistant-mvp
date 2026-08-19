# Start here

## Option A — Docker Compose (preferred)

1. Copy `.env.example` to `.env`.
2. Run `docker compose build`.
3. Start infrastructure: `docker compose up -d postgres redis minio minio-init worker`.
4. Generate the Laravel key: `docker compose run --rm api php artisan key:generate --show`.
5. Put that value in `APP_KEY` inside `.env`.
6. Run `docker compose up -d`.
7. Seed the demo account: `docker compose exec api php artisan db:seed`.
8. Open `http://localhost:3000` and sign in with `developer@example.com` / `password`.
9. Create a meeting and upload `samples/sample-meeting.vtt`.

## Option B — ServBay on Windows

Use this when Docker Desktop / WSL cannot run. Follow **[docs/servbay-local-setup.md](docs/servbay-local-setup.md)** for infrastructure (Postgres, Redis, MinIO), dependency installs, localhost env files, migrations, and the four process terminals (API, queue, AI worker, web).

## Notes

The default transcription backend is `mock` for audio/video, while VTT and text transcripts are genuinely parsed. Set `TRANSCRIPTION_BACKEND=openai` and provide `OPENAI_API_KEY` to enable diarized audio transcription.
