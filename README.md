# Restano

Multi-tenant SaaS for cafes, restaurants and bakeries to publish digital menus reached through QR codes (Laravel 12, PostgreSQL, Redis, Filament 5, Blade + Tailwind + Alpine).

- Rules and architecture decisions: [CLAUDE.md](CLAUDE.md) and [docs/architecture/](docs/architecture/)
- Launch work, phase by phase: [docs/launch-plan.md](docs/launch-plan.md)

## Development

Everything runs in Docker Compose (`docker-compose.yml`). Never run PHP, Composer, Artisan or npm on the host. The services, the container names and every command are in [CLAUDE.md §9](CLAUDE.md#9-docker-execution-strategy). First-time setup:

```bash
cp .env.example .env
docker compose up -d --build
docker exec restano-php-fpm-1 composer install
docker exec -u www-data -e HOME=/tmp restano-php-fpm-1 php artisan key:generate
docker exec restano-postgres-1 createdb -U root restano_test
docker exec -u www-data -e HOME=/tmp restano-php-fpm-1 php artisan migrate --seed
docker compose run --rm node npm ci
docker compose run --rm node npm run build
```

- Site: http://localhost:37000. Mail catcher: http://localhost:8025.
- Local seeding also creates demo tenants and users (`DemoSeeder`, password `password`).
- Grant yourself `/admin` access: `php artisan platform:assign-role you@example.com super_admin` (in the php-fpm container, as `www-data`).
- Tests: `php artisan test` (sequential, about 2.5 minutes).

## Production

One VPS, Docker Compose with [docker-compose.prod.yml](docker-compose.prod.yml), code bind-mounted from a git checkout on the server.

| Service | What it does |
|---|---|
| `webserver` | nginx ([phpdocker/nginx/nginx.prod.conf.template](phpdocker/nginx/nginx.prod.conf.template)), port `${HTTP_PORT:-80}`, serves `${CENTRAL_DOMAIN}` and `*.${CENTRAL_DOMAIN}`, drops other hosts |
| `php-fpm` | PHP runtime image ([phpdocker/php-fpm/Dockerfile.prod](phpdocker/php-fpm/Dockerfile.prod)): OPcache without timestamp checks, production `php.ini` |
| `queue` / `scheduler` | `queue:work redis` and `schedule:work` as `www-data` |
| `postgres` / `redis` | no ports outside the Compose network; Redis requires `REDIS_PASSWORD` and keeps an append-only file |
| `node` | only for the asset build inside `deploy.sh` (profile `tools`) |

Compose takes the database and Redis credentials from the same `.env` that Laravel reads. Data lives in the volumes `restano_postgres-data` and `restano_redis-data`, plus uploads in `storage/app/public` inside the checkout.

> **Never run `docker compose -f docker-compose.prod.yml down -v`**: it deletes the database.

TLS, HSTS and proxy headers aren't set up yet (separate proxy / HTTPS work). Until a TLS proxy sits in front of nginx, the generated URLs use `http://`.

### First deploy

Requirements on the server: Docker Engine with the Compose plugin, git, and DNS for `restano.app` and `*.restano.app` pointing at the server.

```bash
git clone <repository-url> /srv/restano && cd /srv/restano
cp .env.production.example .env
chmod 600 .env
```

Fill in `.env`:

- `APP_KEY`: generate it with `echo "base64:$(openssl rand -base64 32)"`.
- `DB_PASSWORD`, `REDIS_PASSWORD`: long random values, e.g. `openssl rand -hex 24`. Postgres creates the user with this password only when the volume is first created; changing it later needs `ALTER USER` as well.
- `MAIL_*`: the SMTP provider (launch plan Faz 3).

Then deploy, seed the base data once, and create the first super admin:

```bash
scripts/deploy.sh

alias dc='docker compose -f docker-compose.prod.yml'
# Only on the first deploy: PlanSeeder uses updateOrCreate and would overwrite
# plans edited in /admin. Production seeding creates plans and FAQ, no demo data.
dc exec -u www-data -e HOME=/tmp php-fpm php artisan db:seed --force
```

The super admin needs an account first: register through the public site, then grant the role with `platform:assign-role` as above (`dc exec -u www-data -e HOME=/tmp php-fpm php artisan platform:assign-role ...`).

### Every deploy

```bash
cd /srv/restano && scripts/deploy.sh
```

[scripts/deploy.sh](scripts/deploy.sh) runs: maintenance mode on → `git pull --ff-only` → PHP image build (cached) → `composer install --no-dev --optimize-autoloader` → `npm ci && npm run build` in the `node` container → ownership of `storage/` and `bootstrap/cache` → containers up → `storage:link` (first time) → `optimize` (config, events, routes, views) and `filament:optimize` → `migrate --force` → `queue:restart` and a restart of `php-fpm`, `queue` and `scheduler` (OPcache doesn't notice changed files) → maintenance mode off.

It stops at the first error and **leaves the site in maintenance mode**. Fix the cause and run it again, or bring the site back on the current code:

```bash
docker compose -f docker-compose.prod.yml exec -u www-data php-fpm php artisan up
```

### Day-to-day

```bash
alias dc='docker compose -f docker-compose.prod.yml'
dc ps                                   # status and health
dc logs -f --tail 100 webserver php-fpm queue
dc exec -u www-data -e HOME=/tmp php-fpm php artisan <command>
tail -f storage/logs/laravel-$(date +%F).log   # application log (daily, 14 days)
```

- Changes in `phpdocker/php-fpm/` are picked up by the next `scripts/deploy.sh` (it rebuilds the image).
- Changing `.env` needs `artisan optimize` and a restart of `php-fpm queue scheduler`, because the config is cached. The simplest way is to run `scripts/deploy.sh` again.
- Upload limit: 8 MB per request in nginx, 6 MB per file in PHP; the largest image type allows 5 MB (`MediaType::maxKilobytes()`).
