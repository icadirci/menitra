# CLAUDE.md — QR Menu SaaS Platform (Menitra)

Multi-tenant SaaS for cafes, restaurants, bakeries and other hospitality businesses to publish digital menus reached through QR codes. Primary market: **Turkey** (default currency TRY, languages `tr` + `en`).

Brand: **Menitra**, production domain **menitra.com** (logo, icons and colours in [docs/design/brand.md](docs/design/brand.md)). Infrastructure names (containers `restano-*`, databases `restano`/`restano_test`, `/srv/restano`) intentionally keep `restano`.

This file holds the binding rules. The approved architecture (v0.2) is documented in detail in [docs/architecture/](docs/architecture/) — read the relevant document before working on an area:

| Area | Document |
|---|---|
| System overview, request flows, directory structure, roadmap | [overview.md](docs/architecture/overview.md) |
| Tenant identification, isolation, queues, security | [tenancy.md](docs/architecture/tenancy.md) |
| Schema, relationships, integrity constraints, translations | [database.md](docs/architecture/database.md) |
| Locations, menus, QR codes, public rendering | [menu-system.md](docs/architecture/menu-system.md) |
| Theme system | [themes.md](docs/architecture/themes.md) |
| Redis caching and invalidation | [caching.md](docs/architecture/caching.md) |
| Plans, subscription lifecycle, grace period | [subscriptions.md](docs/architecture/subscriptions.md) |
| Filament panels, roles, panel authorization | [overview.md § Admin and business panels](docs/architecture/overview.md#admin-and-business-panels) |

Area-specific rules live in [.claude/rules/](.claude/rules/) (backend, frontend, database, security).

## 1. Scope

**In scope now:** public marketing website, pricing page, contact page (queued mail, no storage), registration, forgot password, email verification, a minimal read-only `/account` page, public QR menu pages, stable QR redirects, eight menu themes (three premium ones added 2026-11-19), the **Filament panel infrastructure** (`/admin` for the platform and `/app/{tenant-slug}` for businesses, each with login and an initial dashboard), and the **business management modules in `/app`** (Phase B, built step by step: business settings and languages, locations, menus and content, branch operations, QR codes, themes, dashboard — see [overview.md § Business panel](docs/architecture/overview.md#business-panel-phase-b)).

**In scope for launch (decided 2026-11-16, built per [docs/launch-plan.md](docs/launch-plan.md)):**
- **Team management in `/app`** (launch plan Faz 7): owner-only invitations by email (`tenant_invitations`), role and branch assignment, deactivating/removing members; member count limited by the `max_members` plan feature. No owner transfer and no manager-initiated invitations in the first version.
- **Platform management in `/admin`** (launch plan Faz 8, built): tenant management (list, detail, suspend/unsuspend, slug change with 301 from former slugs, soft delete/restore by super admins only), user management (list, detail, resend verification, create platform users, platform roles, deactivation), an append-only `admin_audit_events` log and **limited impersonation**. `platform_staff` only reads and resends verification mails. Subscriptions and payments stay read-only in `/admin` (refunds in the iyzico merchant panel).

**Waiter calls (requested 2026-11-18, built step by step):** table QR codes (`location_tables`, one code per table, `max_tables`: Başlangıç 0, Profesyonel 30, Çoklu Şube unlimited — **done**), a `waiter_call` plan feature (Profesyonel + Çoklu Şube) with one tenant-wide on/off switch on the theme/appearance page (`tenants.waiter_calls_enabled`, `WaiterCallService` — **done**; the feature stays `isAvailable() = false` until guests can call), then a signed short-lived table token passed in the redirect's URL fragment, a stateless throttled call endpoint (reasons: waiter, bill), a polling calls screen in `/app` scoped to the member's branches, and call records pruned after 90 days. See [menu-system.md § Waiter calls](docs/architecture/menu-system.md#waiter-calls).

**Out of scope — do not build unless explicitly requested:**
- CRUD modules (Filament resources) in `/admin` beyond the ones listed here. Exists: the admin panel infrastructure, its initial dashboard, and the **marketing site content resources** requested on 2026-11-15 — plans (prices, limits, translations; super admins only), marketing FAQ and testimonials (any platform role), and the **site settings page** (platform contact details: email, phone/WhatsApp, address, support hours, social links; super admins only). Tenant/user management and impersonation are in scope for launch (above).
- Automatic recurring charges (iyzico Subscription API or stored cards), mid-period downgrades, refunds from the app and e-Arşiv invoice integration. **Built (2026-11-18):** manual period payments through iyzico Checkout Form (owner pays each period in `/app`, immediate prorated upgrades during a paid period, optional "pay now" at registration, receipts, reminder mails, webhook + reconciliation) — see [subscriptions.md § Payments](docs/architecture/subscriptions.md#payments).
- Online ordering, payment from the menu, table reservations (keep the design extensible for them).
- Custom domains (table and resolver slot are reserved; implementation is a later phase).

## 2. Technology Stack

- **Backend:** PHP 8.4+, Laravel 12, **iyzico/iyzipay-php** (official SDK, only inside `IyzicoGateway`), PostgreSQL, Redis (cache, queue, session on separate DBs), Eloquent, Form Requests, Policies/Gates, Queues, **Laravel Fortify** (headless auth with our own Blade views for public registration, password reset and email verification), **Filament 5** (the `/admin` and `/app` panels only), **Sentry** (`sentry/sentry-laravel`, error tracking without personal data).
- **Frontend:** Blade (SSR), Tailwind CSS 4, Alpine.js (npm `alpinejs`, public site and QR menus only), Vite, vanilla JS when appropriate. Bundles: `app.css` + `app.js` (public site, auth); `menu-base.css` + one `themes/{key}.css` + `menu.js` (QR menus). Filament panels load none of them.
- **Testing:** PHPUnit against a PostgreSQL test database.
- **Livewire exception:** Livewire is installed **only as a Filament dependency** and is used only inside the `/admin` and `/app` panels. Never build Livewire components, or load Livewire assets, in the marketing site, auth pages or public QR menus. Those stay Blade + Tailwind + Alpine.
- **Forbidden unless explicitly requested:** React, Vue, Next, Nuxt, Inertia, Livewire (outside the exception above), Filament plugins, Bootstrap, jQuery, Laravel starter kits, tenancy packages (e.g. `stancl/tenancy`), translation packages (e.g. `spatie/laravel-translatable`).
- **Any new Composer/NPM dependency requires explaining why and getting approval first.**

## 3. Finalized Architecture Decisions

1. **Modular monolith**, server-side rendered. Controllers thin → Form Requests → Services → Models. No repositories, no CQRS.
2. **Tenancy:** single database, shared schema, `tenant_id` column. One tenant = one business. Built in-house: `TenantContext` (scoped singleton), fail-closed `BelongsToTenant` global scope, resolver pipeline middleware.
3. **Tenant identification (v1):** central domain `menitra.com`, subdomains `{slug}.menitra.com`, path `menitra.com/m/{slug}`. In the business panel the URL is `/app/{tenant-slug}`. The slug is an identifier, not authorization: the tenant is set in `TenantContext` only after the user's active membership in it is verified. A raw tenant ID from the client is never trusted. Custom domains later.
4. **Business structure:** tenant → many `locations` (branches) ↔ many `menus` via the `location_menu` pivot. Menus belong to the tenant so they can be shared across branches. Content is shared; operational state (sold-out, hours) is per location.
5. **Menu content:** menus → categories → items → variants; reusable extra groups → extras; allergens and dietary labels are PHP enums stored in pivot tables; featured/popular flags; availability per item and sold-out per location.
6. **Translations:** one `{entity}_translations` table per translatable entity; base tables hold no translatable text. In-house `HasTranslations` trait with fallback (requested → tenant default → first available). Platform locales in `config/locales.php`; per-tenant locales in `tenant_locales`. UI strings in `lang/{locale}/`. App locale `tr`, fallback `en`; new businesses always start with `default_locale = tr`.
7. **Integrity:** every tenant-owned table has `UNIQUE (tenant_id, id)`; tenant-owned children and pivots reference parents with **composite foreign keys** `(tenant_id, parent_id)` so cross-tenant links cannot be stored.
8. **QR codes:** stable `menitra.com/q/{code}` (random, non-sequential) → 302 to the current canonical menu URL computed at scan time by `MenuUrlGenerator`. `QrCodeScanned` event is the analytics hook.
9. **Rendering:** `PublicMenuService` builds a cached, locale-resolved, allow-listed `MenuSnapshot` DTO; time-dependent state (open now, serving now) is computed per request in `LiveMenuState` and never cached.
10. **Themes:** pure presentation. Selected per tenant with an optional location override; per-theme settings (colours, font pair, layout options) in `theme_settings`, validated incl. WCAG AA contrast and written only through `ThemeService`. Themes receive only `MenuSnapshot`, `LiveMenuState`, `ThemeSettings`. Eight themes: `classic`, `minimal` (every plan) and `bistro`, `cafe`, `grid`, `zen`, `coastal`, `lounge` (`premium_themes`: Profesyonel and Çoklu Şube); only themes with views are selectable (the rest show as "coming soon").
11. **Caching:** version-counter invalidation — a tenant content version and a per-location version, both embedded in snapshot keys. All keys built only by `MenuCacheKeys`, prefixed `qrmenu:`.
12. **Queues:** `tenant_id` is injected into every job payload and the context is restored on `JobProcessing` (before unserialization) and always cleared afterwards (`TenantQueueListener`). Jobs never inherit a context; a missing, deleted or suspended tenant fails the job without running it.
13. **Subscriptions:** provider-agnostic lifecycle; payments through iyzico Checkout Form behind `PaymentGateway` (manual, one period per payment, 3-D Secure, no stored cards; `CheckoutService` → `PaymentProcessor` → `SubscriptionService`); monthly/annual prices in TRY (stored in kuruş), trial → active → grace → expired lifecycle, configurable grace period, plan limits via `PlanFeature` enum (incl. `max_qr_codes`, `max_tables`) + `FeatureGate`, always enforced in backend services. Plan limits apply while the live subscription is `trialing`, `active`, `past_due` or `grace`; with no live subscription (expired or none) the tenant cannot **create** new limited resources, but viewing, editing and deleting existing records is never blocked for that reason. After grace the public menu shows a professional unavailable page. **Tenant data is never deleted automatically.**
14. **Registration** (Fortify → `TenantRegistrationService`) creates in one transaction: user, tenant (+ name translation), owner membership, default location, default menu (attached), trial subscription (+ `trial_started` event). Tenant locales, default theme settings and the default QR code are added to the same transaction when those tables exist. Public registration can never grant a platform role.
15. **Panels (Filament 5):** `admin` panel at `/admin` (platform owners and staff) and `app` panel at `/app/{tenant-slug}` (business users, Filament tenancy + tenant switcher). Both use Filament's own login (`/admin/login`, `/app/login`) on the shared `web` guard and session; panel registration is disabled. Both are registered for the central domain only, and so are Livewire's update, upload and preview endpoints. Fortify's own routes are ignored: only registration, password reset and email verification are exposed (`routes/auth.php`); `GET /login` redirects to `/app/login`. Members of a suspended business get an explicit 403 in `/app`; their data is untouched. `/app` requires a verified email (unverified users go to `/email/verify`); `/admin` doesn't. A central `POST /logout` exists for the verification page. Panels are presentation: they call the same services and policies as the rest of the app. **Two-factor (Filament MFA, TOTP + recovery codes) is mandatory in `/admin`** and optional in `/app`. **Impersonation** (launch plan Faz 8) is limited: only a `super_admin`, only into users without a platform role; the impersonator's id is kept in the session and the session ends automatically after 60 minutes; `/app` shows a "viewing as X / leave" band; during impersonation password, email and MFA changes, entering `/admin` and nested impersonation are blocked; start and end are written to `admin_audit_events`; leaving regenerates the session and restores the admin. Admin resources reach across tenants only through the documented scope bypass area (see [tenancy.md § Allowed scope bypasses](docs/architecture/tenancy.md#allowed-scope-bypasses)).
16. **Roles:** platform roles in a nullable `users.platform_role` (`PlatformRole`: `super_admin`, `platform_staff`). Business roles on the membership `tenant_user.role` (`TenantRole`: `owner`, `manager`, `branch_staff`). Branch access through `location_user` (membership ↔ location, composite FKs). Owners reach every branch of their tenant; managers and branch staff only their assigned branches. No roles/permissions package. Access to both panels requires both grants explicitly. **Menu content** (menus, categories, items, variants) is editable by owners, and by a manager only when the menu is attached to at least one location and **every** location it is attached to is assigned to that manager; unattached menus are owner-only. Attaching/detaching menus to locations, extra groups, tenant-wide settings (profile, languages, theme, subscription) and the branch theme override are owner-only. **Team** (launch plan Faz 7): memberships are created by registration (owner), by accepting an invitation, or by artisan/seeders. Only owners invite, change roles and branch assignments, deactivate and remove members. An invitation names an email, a role (`manager` or `branch_staff`) and at least one branch; it is accepted at `GET/POST /invitations/{token}` on the central domain by a signed-in user whose email matches, by a signed-out existing account that confirms its current password (no session is started; it then signs in at `/app/login`), or by a new user who sets a name and password (email then counts as verified). Exactly one owner per tenant; the owner can't remove or demote themselves; `manager` and `branch_staff` need at least one branch; a deactivated member loses access immediately. Active members plus open invitations count against `max_members` (`FeatureGate::ensureCanCreate()` at invitation time).

## 4. Development Principles

- Clean, readable, maintainable code; SOLID where it helps; Laravel conventions; PSR-12; `declare(strict_types=1);`.
- Avoid overengineering and unnecessary abstractions; no service class for trivial operations.
- Use PHP enums for fixed value sets; dependency injection; DB transactions for atomic operations.
- Do not generate unnecessary files, and do not modify unrelated code.
- Mobile-first, accessible, fast pages; premium SaaS look for marketing, distinct identity for menus, shared design system.

## 5. Non-Negotiable Rules

- **Tenant isolation:** never expose or link one tenant's data to another — in queries, routes, cache, files, jobs and authorization. Never trust client-supplied tenant IDs. Scope bypasses are allowed only in the documented places (see tenancy.md).
- **All menu content comes from the database.** Never hardcode business info, products or user-facing strings in templates.
- **Every schema change is a migration.** No destructive schema changes or production data changes without explicit approval.
- **Security:** validate all input, CSRF on, escape output (`{{ }}` only for tenant content), authorize every action, never commit `.env` or secrets, never disable security middleware to bypass errors.
- **Tests:** critical features ship with tests (tenant isolation, registration, auth, public menu access/visibility, cache invalidation, subscriptions, authorization). Never claim tests passed without running them.

## 6. Workflow

1. Inspect the existing structure and read the relevant files and architecture docs.
2. Propose a plan for non-trivial work; ask when requirements are ambiguous instead of making major architectural assumptions.
3. Implement in small, logical steps — never the whole application at once.
4. Run tests and check for regressions.
5. Summarize changed files and decisions.

- If a task conflicts with these rules or the architecture docs, explain the conflict before proceeding.
- When an architectural decision changes, update the relevant doc in `docs/architecture/` in the same change.
- Do not commit unless explicitly requested. Never modify environment configuration without explaining the impact.

## 7. Roadmap

Work proceeds phase by phase with a review pause between phases (details in [overview.md](docs/architecture/overview.md#implementation-roadmap)):

0. Environment (Docker Compose: PHP 8.5, PostgreSQL, Redis — see §9) → P. Panels (Filament install, `admin` + `app` panels, platform roles, `TenantContext`, memberships, locations, branch authorization) → **B. Business panel** (`/app` modules, steps B0–B10; pulls the needed parts of Phases 1–3 forward: `tenant_locales`, content migrations, `plan_limits` + `FeatureGate`) → 1. Tenancy core (remainder) → 2. Database foundation (remainder) → 3. Subscriptions (remainder) → 4. Authentication (Fortify registration, reset, verification) → 5. Marketing site → 6. Public menu infrastructure + `classic` theme → 7. Remaining four themes → 8. Hardening.

## 8. Open Decisions (working defaults until confirmed)

- Trial 14 days, grace 7 days.
- Per-location price overrides — deferred.
- S3-compatible media storage in production needs `league/flysystem-aws-s3-v3` (not installed; requires approval). The `media` disk config is ready for it.

**Decided (2026-11-16):** prices are shown KDV-inclusive (display only). Three plans seeded by `PlanSeeder` (see [subscriptions.md § Data model](docs/architecture/subscriptions.md#data-model)): Başlangıç ₺300/month (basic themes `classic` + `minimal` only), Profesyonel ₺500/month (all themes, featured), Çoklu Şube ₺700/month (2 branches included, +₺200 per further branch via `plans.included_locations` / `plan_prices.extra_location_amount`); yearly = 8 × monthly. Unbuilt plan features (`custom_domain`, `qr_analytics`) can't be enabled or advertised (`PlanFeature::isAvailable()`).

**Decided (2026-11-15):** QR images use the already-installed `chillerlan/php-qrcode` directly (SVG + PNG), no other QR package. QR images are rendered on demand (`QrCodeImage`) and not stored, since they encode only the stable `/q/{code}` URL; uploaded tenant images are stored on the `media` disk (see [tenancy.md § File storage](docs/architecture/tenancy.md#file-storage)).
- Marketing site locales: `tr` at `/`, `en` at `/en`; page paths are localised (`/ozellikler` ↔ `/en/features`, `/fiyatlar` ↔ `/en/pricing`, see [overview.md § Public marketing website](docs/architecture/overview.md#public-marketing-website)).

## 9. Docker Execution Strategy

Docker Compose (`docker-compose.yml`, project `restano`, images in `phpdocker/`) is the **authoritative development environment**. Laravel Sail is not used.

### Services (verified)

| Service | Container | Image | Host port | Purpose |
|---|---|---|---|---|
| `php-fpm` | `restano-php-fpm-1` | `phpdocker/php-fpm` (PHP 8.5 from the `ondrej/php` PPA, Ubuntu 24.04, Composer 2) | — | PHP, Composer, Artisan, PHPUnit |
| `webserver` | `restano-webserver-1` | `nginx:alpine` | `37000` → 80 | serves `public/` |
| `postgres` | `restano-postgres-1` | `postgres:15-alpine` | `37004` → 5432 | database |
| `redis` | `restano-redis-1` | `redis:alpine` | — | cache / queue / session |

Inside the Compose network, services reach each other by service name (`postgres:5432`, `redis:6379`), not `127.0.0.1`.

- Postgres data lives in the named volume `restano_postgres-data` (survives container removal; `docker compose down -v` would delete it — never use `-v`). Back up with `pg_dump -Fc` into `storage/app/private/backups/` (git-ignored).
- PHP extensions are installed in `phpdocker/php-fpm/Dockerfile` as `php8.5-*` apt packages (e.g. `php8.5-intl`, `php8.5-bcmath`); rebuild with `docker compose up -d --build php-fpm`.
- Databases: `restano` (development) and `restano_test` (PHPUnit, configured in `phpunit.xml`). If the test DB is ever missing: `docker exec restano-postgres-1 createdb -U root restano_test`.
- Redis logical DBs: `0` default/locks, `1` cache, `2` queue, `3` session.

| Service | Container | Purpose |
|---|---|---|
| `queue` | `restano-queue-1` | `php artisan queue:work redis` as `www-data` (same image as php-fpm). **Restart after code changes:** `docker compose restart queue` |
| `scheduler` | `restano-scheduler-1` | `php artisan schedule:work` as `www-data` |
| `mailpit` | `restano-mailpit-1` | Mail catcher (`axllent/mailpit:v1.33.1`): SMTP `mailpit:1025` inside the network, UI at http://localhost:8025 (bound to 127.0.0.1) |

### PHP, Composer, Artisan, tests → `php-fpm` container

PHP-FPM runs as `www-data`, which owns `storage/` and `bootstrap/cache`. Run **Artisan and tests as `www-data`** so files they create (compiled views, logs, cache) stay writable by the web server. Composer runs as root (`vendor/` is root-owned). Run without `-it` for non-interactive commands (agents have no TTY).

```bash
docker exec -u www-data -e HOME=/tmp restano-php-fpm-1 php artisan migrate
docker exec -u www-data -e HOME=/tmp restano-php-fpm-1 php artisan test
docker exec restano-php-fpm-1 vendor/bin/pint     # root: rewrites source files
docker exec restano-php-fpm-1 composer install
docker exec -it restano-php-fpm-1 bash          # interactive shell
```

If pages fail with `tempnam(): file created in the system's temporary directory`, storage ownership was lost: `docker exec restano-php-fpm-1 sh -c 'chown -R www-data:www-data storage bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache'`.

### Node.js / NPM → `node` service

On-demand service (`node:24-bookworm-slim`, Node 24 LTS; Vite 7 needs ≥ 22.12), profile `tools`, so `docker compose up` doesn't start it. `node_modules/` and `public/build/` are produced inside this container only.

```bash
docker compose run --rm node npm ci                          # install from package-lock.json
docker compose run --rm node npm run build                   # production build → public/build
docker compose run --rm --service-ports node npm run dev     # Vite dev server on :5173 (HMR)
docker compose run --rm node npm install -D <pkg>            # new dependencies still need approval
```

Stop `npm run dev` with **Ctrl+C** so Vite removes `public/hot`. If the container is killed instead, delete `public/hot`, or Laravel keeps pointing assets at the stopped dev server.

### Rules

- Never run PHP, Composer, Artisan, PHPUnit, Node or NPM on the host, even when the host has them installed. The host's Node (nvm) must not be used for this project.
- Before running commands, check that the container is up (`docker compose ps`). If it is down or a command fails for environment reasons, report it and ask. Never switch to the host silently.
- Treat versions inside the containers as the source of truth (`php -v`, `php -m`, `composer --version`, and once added `node -v`, `npm -v`).
- Never run `docker compose down -v` or delete Docker volumes (data loss).
- Changes to `docker-compose.yml`, `phpdocker/*` or images, and installing system packages or PHP extensions, require approval first.
