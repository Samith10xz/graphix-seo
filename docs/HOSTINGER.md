# Running on Hostinger (Cloud Startup)

## How it fits shared/cloud hosting

- **No long-running workers.** One cron line runs `php artisan schedule:run` every minute. The
  scheduler then (a) processes the database queue for up to 55 seconds and (b) runs
  `app:post-deploy` (migrations + housekeeping once per new commit).
- **Crawls run in chunks.** `CrawlChunk` fetches `CRAWL_CHUNK_SIZE` pages (default 20, five at a
  time), stores them, and queues the next chunk. No single job runs long.
- **Document root.** The repo is deployed into the subdomain folder. The root `.htaccess` sends
  every request into `public/`, so `.env`, `vendor/` and source files are never served.

## Limits to know

| | Hostinger | Move to VPS when… |
|---|---|---|
| Pages per crawl | up to `CRAWL_MAX_PAGES` (default 1,000) | clients need 5,000+ page crawls |
| Parallel crawls | a few at once (one queue pass per minute) | many customers crawl at the same time |
| JavaScript sites | not rendered | clients run React/Vue-only sites |

## Setup (done once)

1. **PHP 8.2+** for the site (hPanel → Advanced → PHP Configuration).
2. **Database**: create a MySQL database + user in hPanel. Host is `127.0.0.1`.
3. **Git**: hPanel → Advanced → Git → generate the account SSH key; add it to GitHub
   (repo → Settings → Deploy keys, read-only). Deploy `git@github.com:Samith10xz/graphix-seo.git`,
   branch **`release`**, into the `web` folder of the subdomain. The `release` branch is built by
   GitHub Actions with `vendor/` included, so Hostinger never runs Composer.
4. **.env**: copy `.env.example` to `.env` in the deployed folder; fill DB_*, APP_URL and API keys.
   `APP_KEY` is generated automatically on the first cron run.
5. **Cron** (hPanel → Advanced → Cron Jobs), every minute:
   `/usr/bin/php /home/<user>/domains/graphixdesign.agency/public_html/<folder>/artisan schedule:run`
   (use the PHP 8.2+ binary path hPanel shows if the default CLI PHP is older).
6. **First account**: open the site. With no users yet it shows a one-time setup page that creates
   the owner account and workspace.

## Updating

Push to `main` → the "Build release" action updates the `release` branch → redeploy in hPanel.
Within a minute the cron run applies migrations automatically.

## Moving to a VPS later

1. Export the MySQL database from hPanel, import it into the VPS MySQL.
2. Deploy the same repo with `deploy/bootstrap.sh` + `deploy/install.sh`.
3. In `.env` set `CRAWL_ENGINE=python` (docker-compose already does this) and point DNS to the VPS.
