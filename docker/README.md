# Self-hosting with Docker

This is the production stack — one app container (nginx and php-fpm together) plus MySQL. It's a different thing from `compose.yaml` at the repo root, which is [Sail](https://laravel.com/docs/sail) and is for local development only: it bind-mounts your working copy and runs a dev-oriented PHP runtime, neither of which you want on a server.

## First run

```bash
cp .env.example .env.production
```

Edit `.env.production` and set at minimum:

- `APP_URL` — the real URL this will be reachable at. Required; the compose file refuses to start without it.
- `DB_PASSWORD` — a real password. Also required, same reason.
- `APP_KEY` — generate one with `docker run --rm php:8.3-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"` if you don't have PHP locally, or `php artisan key:generate --show` if you do. Also required — a self-hoster starting the stack with any of these three unset gets a clear compose error naming which one, rather than a container that starts, looks fine, and 500s every request.

Then:

```bash
docker compose -f docker-compose.prod.yml --env-file .env.production up -d --build
```

The app container runs pending migrations and rebuilds Laravel's config/route/view caches on every start — safe to do every time, since it's rebuilding from what's already on disk, never resetting data. It does **not** run the demo seeder; a self-hoster's database starts empty except for whatever `migrate` creates. If you want the demo data too, run it once by hand:

```bash
docker compose -f docker-compose.prod.yml --env-file .env.production exec app php artisan db:seed
```

## Putting a reverse proxy in front of it

The app is on `${APP_PORT:-8000}` — `http://your-server:8000` by itself, which is not where this should stay. Two things need a real reverse proxy (Caddy, Traefik, nginx on the host) in front before this is reachable from the internet, not just "recommended":

1. **TLS.** This compose file deliberately doesn't take a position on your TLS setup — there are too many reasonable ways to do it to pick one for you.
2. **`SESSION_SECURE_COOKIE=true` is on by default**, which means the session cookie is marked Secure and the browser drops it over plain HTTP. Concretely: **nobody can log in — to Filament or to `/rep` — until the proxy and TLS are actually in front of this.** It's not a soft recommendation; it's a hard requirement for the app to be usable at all.

Once the proxy is up, tell Laravel to trust it by setting `TRUSTED_PROXIES` in `.env.production` to the proxy's address (or `*` if the app container is reachable only through it, never directly) — otherwise every request looks like plain HTTP to Laravel regardless of what the browser used, and generated URLs come out `http://` on an `https://` page.

## What's in here

- `Dockerfile` — three build stages: composer install (deps only, `--no-dev`), the frontend build (the rep PWA's Vite bundle), then a runtime image with nginx and php-fpm in one container serving both. Nothing dev-oriented ships in the image: no Xdebug, no bind-mounted source, opcache on with timestamp validation off (the image is immutable — nothing changes at runtime that opcache would need to notice).
- `nginx.conf` — a standard Laravel site config, plus one thing specific to this app: `/sw.js` is served with `Cache-Control: no-cache`, because a browser holding onto a stale service worker is a stale offline app for whoever it belongs to.
- `entrypoint.sh` — `migrate --force`, cache rebuild, then hands off to the real command. Never `migrate:fresh` — this runs against whatever is already in the database.

## What's in here

- `Dockerfile` — three build stages: composer install (deps only, `--no-dev`), the frontend build (the rep PWA's Vite bundle), then a runtime image with nginx and php-fpm in one container serving both. Nothing dev-oriented ships in the image: no Xdebug, no bind-mounted source, opcache on with timestamp validation off (the image is immutable — nothing changes at runtime that opcache would need to notice).
- `nginx.conf` — a standard Laravel site config, plus one thing specific to this app: `/sw.js` is served with `Cache-Control: no-cache`, because a browser holding onto a stale service worker is a stale offline app for whoever it belongs to.
- `entrypoint.sh` — `migrate --force`, cache rebuild, then hands off to the real command. Never `migrate:fresh` — this runs against whatever is already in the database.

## What this doesn't do

No queue worker container, no Redis, no scheduler — the app doesn't currently need any of them (sessions, cache and queue all run on the `database` driver already). If a future version does, add the service then; there's no reason to run a Redis container for nothing today.

No automatic nightly reset. That's specific to the public demo deployment, on purpose — a self-hoster running this with their own distributor's real orders and stock would not want their data wiped on a schedule, so nothing in this compose file does that regardless of what the live demo does.

No `ext-zip` in the runtime image — nothing in the app uses it today (no Filament export/import actions are wired up), so this is a gap that costs nothing until the day one is added, at which point it needs adding to the `apk`/`docker-php-ext-install` line in `Dockerfile`, with the same care the `icu-libs` fix nearby took: on this base image, deleting a `-dev` package can silently remove a runtime library nothing else references, and the only way that's been caught here is by actually running the sequence, not by reading it.

## Known limitations

- **Process supervision is minimal.** The container's `CMD` starts php-fpm in the background and nginx in the foreground; if php-fpm dies, the container keeps running and serves 502s until something restarts it, and a plain shell as PID 1 doesn't forward `SIGTERM` cleanly, so `docker stop` takes the slower `SIGKILL` path after its timeout. Fine for the single-instance, `restart: unless-stopped` audience this is built for; a real process supervisor (s6, tini) would close this if it ever needs to be more resilient than that.
