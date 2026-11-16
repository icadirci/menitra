#!/usr/bin/env bash
# Production deploy for the single-VPS Compose stack (docker-compose.prod.yml).
# Run from anywhere inside the server's checkout: scripts/deploy.sh
#
# Stops at the first failing step. The site then stays in maintenance mode on
# purpose (a half-finished deploy shouldn't serve traffic): fix the cause and
# run the script again, or bring the old code back with `artisan up`.
set -euo pipefail

cd "$(dirname "$0")/.."

COMPOSE=(docker compose -f docker-compose.prod.yml)

log() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
fail() { printf '\n\033[1;31mDeploy failed: %s\033[0m\n' "$*" >&2; exit 1; }

artisan() {
    "${COMPOSE[@]}" exec -T -u www-data -e HOME=/tmp php-fpm php artisan "$@"
}

on_error() {
    printf '\n\033[1;31mDeploy failed at line %s. The site is still in maintenance mode.\033[0m\n' "$1" >&2
    printf 'Fix the cause and re-run scripts/deploy.sh, or: %s exec -u www-data php-fpm php artisan up\n' "${COMPOSE[*]}" >&2
}
trap 'on_error $LINENO' ERR

[[ -f .env ]] || fail ".env is missing (copy .env.production.example and fill it in)."
grep -Eq '^APP_KEY=.+' .env || fail "APP_KEY is empty in .env (see README: first deploy)."
grep -Eq '^APP_ENV=production$' .env || fail "APP_ENV must be production in .env."

if [[ -n "$("${COMPOSE[@]}" ps -q --status running php-fpm 2>/dev/null)" ]]; then
    log "Maintenance mode on"
    artisan down --retry=30
else
    log "php-fpm is not running (first deploy?); skipping maintenance mode"
fi

log "Pulling code"
git pull --ff-only

log "Building the PHP image (cached unless phpdocker/php-fpm changed)"
"${COMPOSE[@]}" build php-fpm

log "Starting Postgres and Redis"
"${COMPOSE[@]}" up -d --wait postgres redis

log "Installing Composer dependencies (no dev)"
"${COMPOSE[@]}" run --rm --no-deps -T php-fpm \
    composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist

log "Building front-end assets"
"${COMPOSE[@]}" run --rm --no-deps -T node sh -c 'npm ci --no-audit --no-fund && npm run build'

log "Fixing ownership of storage/ and bootstrap/cache"
"${COMPOSE[@]}" run --rm --no-deps -T php-fpm \
    sh -c 'chown -R www-data:www-data storage bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache'

log "Starting application containers"
"${COMPOSE[@]}" up -d --remove-orphans php-fpm webserver queue scheduler

log "Caching config, routes, events, views and Filament components"
# public/ isn't writable by www-data; the symlink is created once as root.
[[ -L public/storage ]] || "${COMPOSE[@]}" exec -T php-fpm php artisan storage:link
artisan optimize
artisan filament:optimize

log "Running migrations"
artisan migrate --force

log "Restarting PHP (OPcache) and workers"
artisan queue:restart
"${COMPOSE[@]}" restart php-fpm queue scheduler

log "Maintenance mode off"
artisan up

log "Deploy finished: $(git rev-parse --short HEAD)"
