#!/usr/bin/env bash
# Clone (or update) the app, generate secrets on first run, build and start everything.
# Run as the 'deploy' user:  sudo -iu deploy bash /opt/graphix-seo/deploy/install.sh <app-url>
set -euo pipefail

APP_URL="${1:-http://$(curl -fsS https://api.ipify.org)}"
DIR=/opt/graphix-seo

if [ ! -d "$DIR/.git" ]; then
  git clone git@github.com:Samith10xz/graphix-seo.git "$DIR"
else
  git -C "$DIR" pull --ff-only
fi
cd "$DIR"

# First run only: create .env with strong random secrets (never committed)
if [ ! -f .env ]; then
  DB_PASSWORD=$(openssl rand -hex 24)
  DB_ROOT_PASSWORD=$(openssl rand -hex 24)
  ENGINE_KEY=$(openssl rand -hex 32)
  cp .env.example .env
  sed -i "s|^DB_DATABASE=.*|DB_DATABASE=graphix_seo|; s|^DB_USERNAME=.*|DB_USERNAME=graphix|; s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|; s|^DB_ROOT_PASSWORD=.*|DB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}|; s|^ENGINE_API_KEY=.*|ENGINE_API_KEY=${ENGINE_KEY}|; s|^APP_URL=.*|APP_URL=${APP_URL}|" .env
  chmod 600 .env
  echo "Created .env. Add API keys later in $DIR/.env"
fi

docker compose up -d --build
docker compose exec -T app composer install --no-dev --optimize-autoloader --no-interaction
grep -q '^APP_KEY=base64' .env || docker compose exec -T app php artisan key:generate --force

# Wait for MySQL, then migrate
for i in $(seq 1 30); do
  docker compose exec -T app php artisan migrate --force && break
  echo "Waiting for database... ($i)"; sleep 3
done

docker compose exec -T app chown -R www-data:www-data storage bootstrap/cache
docker compose exec -T app php artisan config:cache
docker compose restart queue scheduler

echo
docker compose ps
echo
echo "Done. Check: curl -s ${APP_URL}/up"
