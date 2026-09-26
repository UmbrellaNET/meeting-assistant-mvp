#!/usr/bin/env sh
set -eu

[ -f .env ] || cp .env.example .env

echo "Building containers..."
docker compose build

echo "Starting infrastructure..."
docker compose up -d postgres redis minio minio-init worker

echo "Generate an application key with:"
echo "  docker compose run --rm api php artisan key:generate --show"
echo "Copy it into APP_KEY in .env, then run:"
echo "  docker compose up -d"
echo "  docker compose exec api php artisan migrate --seed"
