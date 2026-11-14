# CLAUDE.md — QR Menu SaaS Platform (Restano)

Multi-tenant SaaS for cafes, restaurants, bakeries and other hospitality businesses to publish digital menus reached through QR codes. Primary market: **Turkey** (default currency TRY, languages `tr` + `en`).

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

Area-specific rules live in [.claude/rules/](.claude/rules/) (backend, frontend, database, security).

## 1. Scope

**In scope now:** public marketing website, pricing page, registration, login, forgot password, a minimal read-only `/account` page, public QR menu pages, stable QR redirects, five menu themes.

**Out of scope — do not build unless explicitly requested:**
- Admin dashboard or tenant management dashboard (the architecture must allow adding one later).
- Payment gateway integration (keep the provider-agnostic `PaymentGateway` contract only).
- Online ordering, payment from the menu, waiter calling, table reservations (keep the design extensible for them).
- Custom domains (table and resolver slot are reserved; implementation is a later phase).

## 2. Technology Stack

- **Backend:** PHP 8.4+, Laravel 12, PostgreSQL, Redis (cache, queue, session on separate DBs), Eloquent, Form Requests, Policies/Gates, Queues, **Laravel Fortify** (headless auth with our own Blade views).
- **Frontend:** Blade (SSR), Tailwind CSS 4, Alpine.js, Vite, vanilla JS when appropriate.
- **Testing:** PHPUnit against a PostgreSQL test database.
- **Forbidden unless explicitly requested:** React, Vue, Next, Nuxt, Inertia, Livewire, Bootstrap, jQuery, Laravel starter kits, tenancy packages (e.g. `stancl/tenancy`), translation packages (e.g. `spatie/laravel-translatable`).
- **Any new Composer/NPM dependency requires explaining why and getting approval first.**

## 3. Finalized Architecture Decisions

1. **Modular monolith**, server-side rendered. Controllers thin → Form Requests → Services → Models. No repositories, no CQRS.
2. **Tenancy:** single database, shared schema, `tenant_id` column. One tenant = one business. Built in-house: `TenantContext` (scoped singleton), fail-closed `BelongsToTenant` global scope, resolver pipeline middleware.
3. **Tenant identification (v1):** central domain `restano.app`, subdomains `{slug}.restano.app`, path `restano.app/m/{slug}`. Authenticated requests resolve the tenant from the user's membership, never from request input. Custom domains later.
4. **Business structure:** tenant → many `locations` (branches) ↔ many `menus` via the `location_menu` pivot. Menus belong to the tenant so they can be shared across branches. Content is shared; operational state (sold-out, hours) is per location.
5. **Menu content:** menus → categories → items → variants; reusable extra groups → extras; allergens and dietary labels are PHP enums stored in pivot tables; featured/popular flags; availability per item and sold-out per location.
6. **Translations:** one `{entity}_translations` table per translatable entity; base tables hold no translatable text. In-house `HasTranslations` trait with fallback (requested → tenant default → first available). Platform locales in `config/locales.php`; per-tenant locales in `tenant_locales`. UI strings in `lang/{locale}/`.
7. **Integrity:** every tenant-owned table has `UNIQUE (tenant_id, id)`; tenant-owned children and pivots reference parents with **composite foreign keys** `(tenant_id, parent_id)` so cross-tenant links cannot be stored.
8. **QR codes:** stable `restano.app/q/{code}` (random, non-sequential) → 302 to the current canonical menu URL computed at scan time by `MenuUrlGenerator`. `QrCodeScanned` event is the analytics hook.
9. **Rendering:** `PublicMenuService` builds a cached, locale-resolved, allow-listed `MenuSnapshot` DTO; time-dependent state (open now, serving now) is computed per request in `LiveMenuState` and never cached.
10. **Themes:** pure presentation. Selected per tenant with an optional location override. Themes receive only `MenuSnapshot`, `LiveMenuState`, `ThemeSettings`. Five themes: `classic`, `minimal`, `bistro`, `cafe`, `grid`.
11. **Caching:** version-counter invalidation — a tenant content version and a per-location version, both embedded in snapshot keys. All keys built only by `MenuCacheKeys`, prefixed `qrmenu:`.
12. **Queues:** `tenant_id` is injected into every job payload and the context is restored on `JobProcessing` (before unserialization) and always cleared afterwards.
13. **Subscriptions:** provider-agnostic; monthly/annual prices in TRY (stored in kuruş), trial → active → grace → expired lifecycle, configurable grace period, plan limits via `PlanFeature` enum + `FeatureGate`. After grace the public menu shows a professional unavailable page. **Tenant data is never deleted automatically.**
14. **Registration** (Fortify → `TenantRegistrationService`) creates in one transaction: user, tenant, tenant locales, owner membership, default location, default menu (attached), default theme settings, trial subscription, default QR code.

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

0. Environment (Sail: PHP 8.4, PostgreSQL, Redis; Fortify, Alpine; config) → 1. Tenancy core → 2. Database foundation → 3. Subscriptions → 4. Authentication → 5. Marketing site → 6. Public menu infrastructure + `classic` theme → 7. Remaining four themes → 8. Hardening.

## 8. Open Decisions (working defaults until confirmed)

- Plan names, prices and limits — placeholder seeds until defined.
- Pricing shown KDV-inclusive or "+KDV" — display only.
- Trial 14 days, grace 7 days.
- Email verification requirement timing.
- QR image generation library (`chillerlan/php-qrcode` proposed, **not approved**).
- Per-location price overrides — deferred.
- Marketing site locales: `tr` at `/`, `en` at `/en`.
