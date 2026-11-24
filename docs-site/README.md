# Graphix Docs (docs.graphixdesign.agency)

Private documentation for every Graphix project: URLs, hosting, admin panels, repos, DNS,
integrations and runbooks. Built on [BookStack](https://www.bookstackapp.com/) (MIT licence).

- `BOOKSTACK_VERSION` — the BookStack release that is deployed. Change it and push to upgrade.
- `root.htaccess` — copied to the release root so only `public/` is served.
- The `Build docs release` workflow builds BookStack with `vendor/` into the `docs-release` branch.
  Hostinger deploys that branch into `public_html/docs/app`.
- Secrets live only in `.env` on the server. **Passwords never go in the docs**: record where the
  login lives in the password manager instead.
- A cron job runs `php artisan migrate --force` every 15 minutes so upgrades apply their database changes.
