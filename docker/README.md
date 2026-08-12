# Self-hosting with Docker

This is the production stack — one app container (nginx and php-fpm together) plus MySQL. It's a different thing from `compose.yaml` at the repo root, which is [Sail](https://laravel.com/docs/sail) and is for local development only: it bind-mounts your working copy and runs a dev-oriented PHP runtime, neither of which you want on a server.

## First run

```bash
cp .env.example .env.production
```

Edit `.env.production` and set at minimum:

- `APP_URL` — the real URL this will be reachable at.
- `DB_PASSWORD` — a real password. There is no default; the stack won't come up without one.
- `APP_KEY` — generate one with `docker run --rm php:8.3-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"` if you don't have PHP locally, or `php artisan key:generate --show` if you do.

Then:

```bash
docker compose -f docker-compose.prod.yml --env-file .env.production up -d --build
```

The app container runs pending migrations and rebuilds Laravel's config/route/view caches on every start — safe to do every time, since it's rebuilding from what's already on disk, never resetting data. It does **not** run the demo seeder; a self-hoster's database starts empty except for whatever `migrate` creates. If you want the demo data too, run it once by hand:

```bash
docker compose -f docker-compose.prod.yml --env-file .env.production exec app php artisan db:seed
```

The app is on `${APP_PORT:-8000}` — `http://your-server:8000` unless you put a real reverse proxy (Caddy, Traefik, nginx on the host) in front of it for TLS, which is what you should do before this is reachable from the internet. This compose file deliberately doesn't take a position on your TLS setup; there are too many reasonable ways to do it to pick one for you.

## What's in here

- `Dockerfile` — three build stages: composer install (deps only, `--no-dev`), the frontend build (the rep PWA's Vite bundle), then a runtime image with nginx and php-fpm in one container serving both. Nothing dev-oriented ships in the image: no Xdebug, no bind-mounted source, opcache on with timestamp validation off (the image is immutable — nothing changes at runtime that opcache would need to notice).
- `nginx.conf` — a standard Laravel site config, plus one thing specific to this app: `/sw.js` is served with `Cache-Control: no-cache`, because a browser holding onto a stale service worker is a stale offline app for whoever it belongs to.
- `entrypoint.sh` — `migrate --force`, cache rebuild, then hands off to the real command. Never `migrate:fresh` — this runs against whatever is already in the database.

## What this doesn't do

No queue worker container, no Redis, no scheduler — the app doesn't currently need any of them (sessions, cache and queue all run on the `database` driver already). If a future version does, add the service then; there's no reason to run a Redis container for nothing today.

No automatic nightly reset. That's specific to the public demo deployment, on purpose — a self-hoster running this with their own distributor's real orders and stock would not want their data wiped on a schedule, so nothing in this compose file does that regardless of what the live demo does.
