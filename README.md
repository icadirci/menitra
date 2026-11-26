# Menitra

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
- Local seeding also creates demo tenants and users (`DemoSeeder`, password `password`); the theme showcase businesses come from `ShowcaseSeeder`, which `DemoSeeder` calls.
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

TLS ends at the **host's nginx** (the server already routes other projects with it), which proxies `menitra.com` and `*.menitra.com` to the Compose webserver on `127.0.0.1:${HTTP_PORT}`. See [Reverse proxy and HTTPS](#reverse-proxy-and-https).

### Reverse proxy and HTTPS

In `.env`: `HTTP_PORT=127.0.0.1:37100` (any free port, localhost only, so TLS can't be bypassed) and `TRUSTED_PROXIES` set to the Docker bridge the proxied requests come from (default `172.16.0.0/12`; the exact gateway is in `docker network inspect restano_default`). Laravel trusts only `X-Forwarded-For`, `-Proto` and `-Port` from those addresses; `X-Forwarded-Host` is ignored, the `Host` header decides the tenant.

Host nginx server block (certificate paths are examples; `*.menitra.com` needs a wildcard certificate, i.e. certbot with your DNS provider's plugin, DNS-01):

```nginx
server {
    listen 80;
    server_name menitra.com *.menitra.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    http2 on;
    server_name menitra.com *.menitra.com;

    ssl_certificate     /etc/letsencrypt/live/menitra.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/menitra.com/privkey.pem;

    client_max_body_size 8M;   # same as the container nginx; the default 1M breaks uploads

    location / {
        proxy_pass http://127.0.0.1:37100;
        proxy_set_header Host              $host;   # required: tenant resolution and the container's server_name
        proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Port  $server_port;
    }
}
```

Don't add HSTS here: the app sends it on HTTPS responses (`SecurityHeaders`). Check after the first deploy: `curl -sI https://menitra.com/up` shows `Strict-Transport-Security`, and links in mails start with `https://menitra.com`.

### First deploy

Requirements on the server: Docker Engine with the Compose plugin, git, and DNS for `menitra.com` and `*.menitra.com` pointing at the server.

```bash
git clone <repository-url> /srv/restano && cd /srv/restano
cp .env.production.example .env
chmod 600 .env
```

Fill in `.env`:

- `APP_KEY`: generate it with `echo "base64:$(openssl rand -base64 32)"`.
- `DB_PASSWORD`, `REDIS_PASSWORD`: long random values, e.g. `openssl rand -hex 24`. Postgres creates the user with this password only when the volume is first created; changing it later needs `ALTER USER` as well.
- `MAIL_*`: the SMTP provider (launch plan Faz 3).
- `SENTRY_LARAVEL_DSN`, `OPS_ALERT_EMAIL`: error tracking and health alerts (see [Monitoring](#monitoring)).

Then deploy, seed the base data once, and create the first super admin:

```bash
scripts/deploy.sh

alias dc='docker compose -f docker-compose.prod.yml'
# Only on the first deploy: PlanSeeder uses updateOrCreate and would overwrite
# plans edited in /admin. Production seeding creates plans and FAQ, no demo data.
dc exec -u www-data -e HOME=/tmp php-fpm php artisan db:seed --force

# Theme showcase businesses for /temalar (no users, complimentary subscription
# until 2099 on the non-public "demo" plan). Idempotent: existing businesses
# and an existing "demo" plan are left untouched, so it is safe to run again.
dc exec -u www-data -e HOME=/tmp php-fpm php artisan db:seed --class=ShowcaseSeeder --force
```

Create the first super admin without a business (public registration always creates one):

```bash
# Asks for the name and the password (hidden, twice).
dc exec -u www-data -e HOME=/tmp php-fpm php artisan platform:create-user admin@menitra.com
# Non-interactive shell: generates a password and prints it once.
dc exec -T -u www-data -e HOME=/tmp php-fpm php artisan platform:create-user admin@menitra.com --name="Platform Yöneticisi" --generate-password
```

Further platform users are created in `/admin` (Users); an existing account gets a role with `platform:assign-role {email} {role}`. `/admin` requires two-factor: at the first `/admin` login the platform user scans the QR code with an authenticator app (TOTP) and stores the recovery codes. Lost authenticator and recovery codes: `platform:reset-two-factor {email}`, after confirming the person's identity; they set it up again at the next login.

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

### Monitoring

Three layers; set up all three before launch.

**Sentry (errors).** Create a PHP/Laravel project in Sentry, put its DSN into `SENTRY_LARAVEL_DSN` and redeploy. Every exception Laravel reports also goes to Sentry ([config/sentry.php](config/sentry.php)); without a DSN nothing is sent. Expected states are not reported: 404s, validation errors and offline menus (`MenuUnavailableException`). Nothing personal leaves the server: no user data or IP, no cookies, no request or response bodies, no query strings, only a few allow-listed request headers, no SQL bindings or stack frame variables, and the token parts of `/reset-password/…`, `/email/verify/…` and `/invitations/…` are replaced with `[redacted]` ([SentryEventScrubber](app/Support/Monitoring/SentryEventScrubber.php)). Events carry the internal `tenant_id` as a tag. Tracing and profiling are off. Set up the alert rules (e.g. e-mail for every new issue) in Sentry itself. Sentry is a data processor: list it in the privacy policy (launch plan Faz 10).

**Health check (queue, disk, backups).** The scheduler runs `ops:health-check` every 15 minutes ([config/ops.php](config/ops.php)):

| Check | Problem when | Setting |
|---|---|---|
| Failed queue jobs | at least `OPS_FAILED_JOBS_THRESHOLD` (1) jobs failed since the last alert; each failure is reported once | `php artisan queue:failed`, `queue:retry`, `queue:flush` |
| Disk usage | the disk holding `storage/` is `OPS_DISK_USAGE_THRESHOLD`% (85) full or more | |
| Latest backup | the newest file under `OPS_BACKUP_PATH` is older than `OPS_BACKUP_MAX_AGE_HOURS` (26) or missing | off until backups exist: `OPS_BACKUP_CHECK=true` (launch plan Faz 4) |

Problems are mailed to `OPS_ALERT_EMAIL`, or to the platform contact e-mail in `/admin` → site settings when it is empty. The mail is sent directly over SMTP, not through the queue (the queue may be the problem). Each check alerts at most once per `OPS_ALERT_COOLDOWN_MINUTES` (60); if the mail fails, the next run retries. Run it by hand to see the current state (exit code 1 while something fails):

```bash
dc exec -u www-data -e HOME=/tmp php-fpm php artisan ops:health-check
```

**Uptime (outside the server).** Point an external monitor (UptimeRobot, Better Stack, …) at `https://menitra.com/up` every 1–5 minutes. It answers 200 when the app boots; if the whole server or Docker is down, nothing on the server can send an alert, so this is the only layer that catches it. Add a second check on a public menu URL to cover the database and Redis.

### Legal and KVKK requests

**Before launch.** The privacy notice and terms (`lang/{tr,en}/legal.php`) are drafts with bracketed placeholders. Fill in the company details and the provider list (hosting, mail provider, Sentry, and where data leaves Turkey the transfer section), have a lawyer review both texts, update their `updated` dates and `marketing.terms_version` in [config/marketing.php](config/marketing.php), then set `MARKETING_LEGAL_REVIEW_PENDING=false` and redeploy. Until then both pages show a "draft" notice.

**Terms acceptance.** Registration and new accounts created from a team invitation require the terms checkbox; `users.terms_accepted_at` and `users.terms_version` record when and which version. The privacy notice is linked as information, not as consent. Accounts created by seeders or artisan, and accounts from before the checkbox existed, have no acceptance recorded. Changing `marketing.terms_version` doesn't ask existing users to accept again (no re-acceptance flow yet); announce material changes by e-mail as the terms say.

**Data subject requests (KVKK m.11).**
1. Requests arrive through the contact form or the application address named in the privacy notice. Log the date received: the legal deadline is **30 days**.
2. Verify identity before acting: the request must come from the account's e-mail address, or the person proves it another way (e.g. a signed or KEP application). Never send data to an unverified address.
3. Find the data in `/admin` (Users → the person and their memberships; Tenants → the business). Contact form messages aren't stored; check the mailbox they were delivered to.
4. Answer in writing within 30 days: what is processed, why, who receives it (the provider list) and how long it is kept.
5. **Deletion:** a business owner's request is handled by soft-deleting the tenant in `/admin` (super admin, audited). Nothing is purged automatically; data stays restorable until a manual, approved purge procedure exists. Deactivate the user account in `/admin` if they should no longer sign in. A member of someone else's business is removed by that business's owner (Team page) or, if they want their account gone, deactivated in `/admin`.
6. Keep the request, the identity check and the answer for your records.
