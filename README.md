# Graphix SEO Platform

A modular SEO planning and audit platform: submit a website, crawl it (Screaming Frog-style),
track keyword usage per page, and work through a step-by-step SEO plan (competitor analysis →
keyword research → … → 90-day roadmap) under one project. Built to be extended per client.

```
graphix-seo/
├── web/            Laravel 11 app — UI, API, accounts, projects, SEO plan steps, AI layer
├── engine/         Python engine — crawler, audits, AEO/GEO checks (FastAPI + Celery)
├── docker/         Dockerfiles and Nginx config
├── docs/           Architecture and decisions
└── docker-compose.yml
```

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for how the parts fit and how to extend them.

## Run on a VPS (Ubuntu 22.04/24.04 + Docker)

```bash
git clone git@github.com:Samith10xz/graphix-seo.git && cd graphix-seo
cp .env.example .env            # shared secrets for docker-compose (DB, engine key)
cp web/.env.example web/.env    # Laravel settings + API keys (Claude, Gemini, DataForSEO)
docker compose up -d --build
docker compose exec app composer install --no-dev --optimize-autoloader
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
```

Open `http://<server-ip>` (put SSL in front with Certbot or Caddy before going live).

### Services

| Service | What it does |
|---|---|
| `nginx` | Public web server (port 80/443) |
| `app` | Laravel (PHP-FPM) |
| `queue` | Laravel queue worker (Redis) |
| `scheduler` | Laravel scheduler (rank checks, re-crawls) |
| `engine` | Python API (crawler, audits) — internal only |
| `engine-worker` | Celery worker running crawls |
| `mysql`, `redis` | Data and queues |

## Secrets

Never commit real keys. Put them in `web/.env` and `.env` on the server only.
Both files are git-ignored.
