# Graphix SEO Platform

A modular SEO planning and audit platform: submit a website, crawl it (Screaming Frog-style),
track keyword usage per page, and work through a step-by-step SEO plan (competitor analysis →
keyword research → … → 90-day roadmap) under one project. Built to be extended per client.

```
graphix-seo/
├── app/ config/ database/ routes/ …   Laravel 12 app (UI, API, plan steps, AI layer, PHP crawler)
├── engine/                             Python crawl engine (optional, VPS only)
├── docker/ docker-compose.yml          VPS deployment
├── deploy/                             VPS setup scripts
└── docs/                               Architecture, Hostinger guide
```

## Runs in two places, same code

| | Hostinger (Cloud Startup) | VPS (Docker) |
|---|---|---|
| Web app | PHP 8.2+ + Apache, deployed from the `release` branch | nginx + PHP-FPM container |
| Crawl engine | `CRAWL_ENGINE=php` — crawls in small queued chunks | `CRAWL_ENGINE=python` — separate engine service |
| Queue | database, run by cron every minute | Redis + always-on worker |
| Database | MySQL from hPanel | MySQL container |

Moving from Hostinger to a VPS later = copy the database, deploy the same repo, change the env.
See [docs/HOSTINGER.md](docs/HOSTINGER.md) and [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Secrets

Never commit real keys. They live only in `.env` on the server (git-ignored).

## Tests

```bash
composer install && php artisan test          # Laravel
cd engine && python -m unittest discover -s tests -t .   # Python engine
```
The PHP and Python page analysers are kept in parity (same issue codes, scores and output).
