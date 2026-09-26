SHELL := /bin/sh

.PHONY: init up down build logs migrate seed test api-test worker-test lint clean

init:
	@test -f .env || cp .env.example .env
	docker compose build
	docker compose up -d postgres redis minio minio-init worker
	docker compose run --rm api php artisan key:generate --show
	@echo "Copy the generated APP_KEY into .env, then run: make up"

up:
	@test -f .env || cp .env.example .env
	docker compose up -d --build

down:
	docker compose down

build:
	docker compose build

logs:
	docker compose logs -f --tail=200

migrate:
	docker compose exec api php artisan migrate

seed:
	docker compose exec api php artisan db:seed

test: api-test worker-test

api-test:
	docker compose exec api php artisan test

worker-test:
	docker compose exec worker pytest -q

lint:
	docker compose exec api sh -lc "find app routes database tests -name '*.php' -print0 | xargs -0 -n1 php -l"
	docker compose exec worker python -m compileall app tests

clean:
	docker compose down -v --remove-orphans
