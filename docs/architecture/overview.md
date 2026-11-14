# Architecture Overview

> Status: **Approved — v0.3** (2026-11-15: Filament panels, roles). v0.2: 2026-11-14. Changes to architectural decisions must update this folder in the same change.

## Contents

- [Product scope](#product-scope)
- [Technology stack](#technology-stack)
- [System context](#system-context)
- [Application layering](#application-layering)
- [Request flows](#request-flows)
- [Authentication](#authentication)
- [Admin and business panels](#admin-and-business-panels)
- [Public marketing website](#public-marketing-website)
- [Directory structure](#directory-structure)
- [Testing strategy](#testing-strategy)
- [Implementation roadmap](#implementation-roadmap)
- [Open decisions](#open-decisions)

Related documents: [tenancy](tenancy.md) · [database](database.md) · [menu system](menu-system.md) · [themes](themes.md) · [caching](caching.md) · [subscriptions](subscriptions.md)

## Product scope

A multi-tenant SaaS platform for cafes, restaurants, bakeries and other hospitality businesses to publish digital menus reached through QR codes. The primary market is Turkey: the default currency is TRY and the initial languages are Turkish (`tr`) and English (`en`).

| In scope (current) | Out of scope (later, architecture must allow it) |
|---|---|
| Public marketing website, pricing page | CRUD modules inside the panels (platform and business management) |
| Registration, forgot password, email verification, minimal `/account` page | Payment gateway integration (iyzico / PayTR) |
| Filament panel infrastructure: `/admin`, `/app/{tenant-slug}`, login, initial dashboards, roles | |
| Public QR menu pages, stable QR redirects | Online ordering, payment from the menu, waiter calling, reservations |
| Five menu themes | Custom domains, QR scan analytics |

## Technology stack

| Layer | Choice |
|---|---|
| Language / framework | PHP 8.4+, Laravel 12 |
| Database | PostgreSQL |
| Cache / queue / session | Redis (separate logical DBs: cache `1`, queue `2`, session `3`) |
| Authentication | Filament login for both panels; Laravel Fortify (headless) with custom Blade views for registration, password reset, email verification. One `web` guard and session |
| Admin / business UI | Filament 5 panels (`admin`, `app`) using Filament's native design system |
| Frontend | Blade (SSR), Tailwind CSS 4, Alpine.js, Vite |
| Tests | PHPUnit on a PostgreSQL test database |
| Local environment | Docker Compose (`php-fpm` PHP 8.5, nginx, PostgreSQL 15, Redis) — see CLAUDE.md §9 |

Not used: React, Vue, Next, Nuxt, Inertia, Bootstrap, jQuery, Laravel starter kits, tenancy packages, translation packages, roles/permissions packages, Filament plugins. **Livewire** is present only as a Filament dependency and is used only inside the panels; the marketing site, auth pages and QR menus never use it. Any new dependency requires approval.

## System context

```
                    ┌──────────────────────────────────────────────────────┐
 restano.app ──────▶│ Central context                                       │
                    │  • Marketing + pricing (tr at /, en at /en)            │
                    │  • Fortify auth, /account (tenant from user membership)│
                    │  • /admin  platform panel (platform_role)              │
                    │  • /app/{tenant-slug}  business panel (membership)     │
                    │  • /q/{code}  stable QR redirect                       │
                    │  • /m/{slug}/…  path-based public menus                │
                    ├──────────────────────────────────────────────────────┤
 {slug}.restano.app▶│ Tenant public menu context                            │
                    │  ResolveTenantFromRequest → TenantContext              │
                    │  → PublicMenuService (Redis) → theme view              │
 custom domain ────▶│  (later phase — resolver slot reserved)                │
                    └──────────────────────────────────────────────────────┘
                          │                 │                   │
                      PostgreSQL          Redis          Storage (local / S3)
                   (shared schema,   (cache, queue,     (tenants/{public_id}/…)
                    tenant_id)         session)
```

The application is a **modular monolith**: one Laravel application, organised by feature, rendered on the server.

## Application layering

| Layer | Responsibility |
|---|---|
| Routes / middleware | Tenant resolution, locale resolution, auth, rate limiting |
| Controllers | HTTP only: call a service, return a response. Kept thin |
| Form Requests | Validation and input authorization |
| Services | Business logic (`TenantRegistrationService`, `PublicMenuService`, `SubscriptionService`, `FeatureGate`, `QrCodeResolver`, …) |
| Models | Entities, relationships, scopes, casts. No complex business logic |
| DTOs (`App\Support\Menu\Data`) | Read-only data contracts for rendering (`MenuSnapshot`) |
| Policies / Gates | Authorization, shared by panels and controllers |
| Filament panels | Presentation for `/admin` and `/app`; call services and policies |
| Observers / events / listeners | Cache invalidation, decoupled side effects |
| Jobs | Async work, always tenant-aware |
| Enums | Fixed value sets |

There are no repositories, no CQRS and no DDD layering. Do not create a service for trivial operations.

## Request flows

**Public menu**
```
GET {slug}.restano.app/{location}/{menu}?lang=en
  → ResolveTenantFromRequest → ResolveMenuLocale → MenuController (thin)
  → PublicMenuService: access check → cached MenuSnapshot + LiveMenuState
  → ThemeRenderer → themes/{key}/menu.blade.php
```

**QR scan**
```
GET restano.app/q/{code} → QrCodeResolver (cached) → 302 to MenuUrlGenerator URL
                         → QrCodeScanned event (analytics hook)
```

**Registration**
```
POST /register → Fortify CreateNewUser → TenantRegistrationService (one transaction)
              → TenantRegistered (after commit) → queued welcome mail
```

Details: [tenancy.md](tenancy.md), [menu-system.md](menu-system.md).

## Authentication

- **Login is Filament's:** `/admin/login` and `/app/login`, both on the `web` guard and the same session. Fortify's own routes are ignored (`Fortify::ignoreRoutes()`); `routes/auth.php` declares only registration, password reset and email verification (Fortify controllers, standard route names, central domain). `GET /login` (named `login`) redirects to `/app/login`, so Laravel's auth redirects and the password-reset response land there. There is no `POST /login` or Fortify logout.
- Fortify features enabled: `registration`, `resetPasswords`, `emailVerification` (when verification is enforced is an open decision). Their views are Blade templates in `resources/views/auth/` (tr/en). After registration, users go to `/app/{tenant-slug}`.
- Panel registration (`->registration()`) and Filament password reset are disabled; registration and reset go through Fortify.
- `App\Actions\Fortify\CreateNewUser` validates only the listed fields and delegates to `TenantRegistrationService::register()`. That method runs **one database transaction** that creates (implemented):
  1. User (never a platform role)
  2. Tenant (with a `tenant_translations` row for its name; slug validated, unique, checked against the reserved list; derived from the business name when left empty)
  3. Owner membership (`tenant_user`, role `owner`)
  4. Default location (`is_default = true`, slug `config('tenancy.default_location_slug')`)
  5. Default menu (type `all_day`, unpublished), attached to the location via `location_menu`
  6. Trial subscription for the default plan, plus a `trial_started` `subscription_events` entry

  Added to the same transaction when their tables exist: `tenant_locales`, default `theme_settings`, the default QR code, and `?plan=` selection.
- After commit, `TenantRegistered` is fired.
- Registration fields: name, email, password, business name, slug (auto-suggested, editable).
- Rate limits: Filament login (5/min), registration (`registration`: 5/min per IP), password reset (`password-reset`: 5/min per email+IP, 20/min per IP), verification links and resends (6/min). The session is regenerated on login.
- `User` implements `MustVerifyEmail`, so registration sends a verification email. Panels don't require a verified email yet (open decision).
- The session cookie is bound to the central domain only (see [tenancy.md](tenancy.md#security-controls)).
- `/account` is minimal and read-only: plan, trial/grace status, public menu link and QR link. **It is not a dashboard.**

## Admin and business panels

Two independent Filament 5 panels, registered only on the central domain (`config('tenancy.central_domain')`):

| | Platform administration | Business management |
|---|---|---|
| Panel ID / provider | `admin` / `App\Providers\Filament\AdminPanelProvider` | `app` / `App\Providers\Filament\AppPanelProvider` |
| URL | `/admin` | `/app/{tenant-slug}` (Filament tenancy, tenant switcher) |
| Login | `/admin/login` | `/app/login` |
| Who | Users with `platform_role` (`super_admin`, `platform_staff`) | Users with an active membership in an active tenant (`owner`, `manager`, `branch_staff`) |
| Code | `app/Filament/Admin/{Pages,Resources,Widgets}` | `app/Filament/App/{Pages,Resources,Widgets}` |
| Look | Filament defaults, own brand name and primary colour | Filament defaults, own brand name and primary colour |

Authorization layers:

1. **Panel:** `User::canAccessPanel()` — `admin` needs `platform_role`; `app` needs an active membership. Access to both panels requires both grants.
2. **Tenant (app):** `User::canAccessTenant()` + `ResolveTenantFromMembership`, see [tenancy.md](tenancy.md#business-panel-tenancy-apptenant-slug).
3. **Branch (app):** `TenantMembership::canAccessLocation()` / `accessibleLocations()`; owners reach every location of the tenant, managers and branch staff only their `location_user` assignments.
4. **Action:** policies (e.g. `LocationPolicy`) combine role and branch access. Every future page and resource also checks `canAccess()` / policies; hiding navigation is never authorization.

Initial business action matrix (extended per resource as CRUD modules are added):

| Action on a location | owner | manager | branch_staff |
|---|---|---|---|
| View | all | assigned | assigned |
| Update | all | assigned | — |
| Create / delete / restore / assign staff | yes | — | — |
| Permanent deletion | — | — | — |

Livewire's update, upload and preview endpoints are restricted to the central domain and to panel users; see [tenancy.md](tenancy.md#livewire-endpoints). Members of a suspended business get an explicit 403 in `/app` and keep their data.

Platform roles: `super_admin` holds every platform permission; `platform_staff` gets limited platform permissions defined per future resource. Platform roles grant nothing in `/app`; there is no global `Gate::before`. Platform roles are assigned with `php artisan platform:assign-role {email} {role}` until a management UI exists.

## Public marketing website

- Central domain only. Marketing routes are not registered on tenant subdomains.
- `layouts/marketing.blade.php` with header, footer and mobile navigation (Alpine).
- Pages:
  - Home
  - Features
  - Pricing (plans read from the DB, cached)
  - Themes showcase (live demo menus from `DemoTenantSeeder`, no hard-coded menu data)
  - Contact (Form Request, rate limited, queued mail)
  - Privacy and Terms
- Locales: Turkish at `/`, English at `/en/…`, set by the `SetMarketingLocale` middleware. All strings come from `lang/{locale}/marketing.php`.
- SEO: per-page meta and Open Graph component, canonical URLs, hreflang between tr and en, `sitemap.xml`.
- Assets: `app.css` and `app.js`, which are never loaded on menu pages.

## Directory structure

```
app/
  Actions/Fortify/           CreateNewUser, ResetUserPassword, UpdateUserPassword
  Console/Commands/          ProcessSubscriptionLifecycle
  Contracts/                 PaymentGateway
  Enums/                     PlatformRole, TenantRole, TenantStatus, MenuType, MenuTheme, Allergen, DietaryLabel,
                             ExtraSelectionType, SubscriptionStatus, SubscriptionEventType,
                             BillingInterval, PlanFeature
  Events/                    TenantRegistered, QrCodeScanned
  Exceptions/                TenantNotResolvedException, MenuUnavailableException
  Filament/
    Admin/                   Pages/Dashboard (platform panel; Resources/, Widgets/ later)
    App/                     Pages/Dashboard (business panel; Resources/, Widgets/ later)
  Http/
    Controllers/
      Marketing/             HomeController, PricingController, ThemeShowcaseController,
                             ContactController, LegalController
      Account/               AccountController
      PublicMenu/            LocationIndexController, LocationController, MenuController,
                             QrRedirectController
    Middleware/              ResolveTenantFromRequest, ResolveTenantFromMembership,
                             ResolveMenuLocale, SetMarketingLocale
    Requests/
  Models/
    Concerns/                BelongsToTenant, HasTranslations
    Translations/            TenantTranslation, LocationTranslation, MenuTranslation, …
    Tenant, TenantDomain, TenantLocale, TenantMembership, User, Location, LocationOpeningHour,
    LocationSpecialHour, LocationItemState, Menu, LocationMenu, LocationMenuHour,
    MenuCategory, MenuItem, MenuItemVariant, ExtraGroup, Extra, MenuItemAllergen,
    MenuItemDietaryLabel, QrCode, ThemeSetting, Plan, PlanPrice, PlanLimit,
    Subscription, SubscriptionEvent
  Observers/                 MenuContentObserver, LocationStateObserver, TenantObserver
  Policies/                  LocationPolicy, Menu, QrCode, …
  Providers/                 AppServiceProvider, FortifyServiceProvider, TenancyServiceProvider,
                             Filament/AdminPanelProvider, Filament/AppPanelProvider
  Services/
    Tenancy/                 TenantRegistrationService, SlugService
    Subscription/            SubscriptionService, FeatureGate
    Menu/                    PublicMenuService, MenuSnapshotBuilder, MenuCache, MenuCacheKeys,
                             LiveMenuState, MenuUrlGenerator, ThemeRenderer
    QrCode/                  QrCodeResolver, QrCodeGenerator
  Support/
    Menu/Data/               MenuSnapshot, TenantBrand, LocationInfo, MenuData, CategoryData,
                             ItemData, VariantData, ExtraGroupData, ExtraData, ThemeSettings
    Money.php
  Tenancy/
    TenantContext.php, ResolvedTenant.php, TenantResolverPipeline.php
    Resolvers/               TenantResolver (interface), SubdomainTenantResolver,
                             PathTenantResolver, (DomainTenantResolver — later)
    Queue/                   TenantQueueListener, TenantAgnostic (interface)
config/                      tenancy.php, locales.php, subscriptions.php, fortify.php
lang/{tr,en}/                auth.php, marketing.php, menu.php, validation.php
resources/
  css/                       app.css, menu-base.css, themes/{classic,minimal,bistro,cafe,grid}.css
  js/                        app.js (marketing + Alpine), menu.js (Alpine only)
  views/
    layouts/                 marketing, auth, menu
    components/{ui,marketing,menu}/
    marketing/  auth/  account/
    menu/                    location-index, unavailable
    themes/{key}/            menu.blade.php, location.blade.php, components/
routes/                      web.php (central), auth.php (Fortify: register, reset, verify), menu.php (subdomain + /m groups), console.php
database/seeders/            PlanSeeder, DemoTenantSeeder, DatabaseSeeder
docs/architecture/           this folder
tests/
  Feature/                   Auth/, Registration/, Tenancy/, PublicMenu/, QrCode/,
                             Subscription/, Cache/, Marketing/
  Unit/                      Enums/, Services/, Support/
  Architecture/              ThemeIndependenceTest, CacheKeyTest
```

**Panels:** management UIs are Filament resources inside the two panels, reusing the existing services, policies and `FeatureGate`. `admin`, `app` and `dashboard` are on the reserved slug list.

## Testing strategy

- PHPUnit with `RefreshDatabase` on a **PostgreSQL** test database. Composite foreign keys, partial unique indexes and `jsonb` must behave as in production.
- The cache uses the `array` store by default. An optional group runs against real Redis.
- Factories build complete tenant graphs (`Tenant::factory()->withLocation()->withMenu()…`).

| Suite | Must cover |
|---|---|
| Tenancy | Tenant A cannot read, bind, update or cache-hit tenant B data; the scope throws without context; queued jobs run under the dispatching tenant only; DB rejects cross-tenant composite FK inserts |
| Registration | One transaction creates every entity or none; slug format, uniqueness and reserved list |
| Auth | Login, logout, rate limits, password reset, email verification |
| Public menu | Subdomain and path resolution; unpublished, hidden, inactive and expired cases; sold-out per location; allow-listed output; locale fallback |
| QR | Redirect follows slug changes; inactive codes; menu detached → location fallback |
| Cache | Warm hit makes zero queries; content edit bumps the tenant version; sold-out bumps the location version only; tenant isolation of keys |
| Subscriptions | Lifecycle transitions, grace period, access-until computation, feature limits |
| Themes | All five render the same fake snapshot with zero queries; architecture test forbids models and services in theme views |
| Authorization | Panel access by platform role / membership; tenant access via `/app/{slug}` (404 for non-members); branch access by assignment; policy matrix by role |

## Implementation roadmap

Each phase ends with tests run, a summary of changed files, and a review pause.

| Phase | Scope | Exit criteria |
|---|---|---|
| 0. Environment | Docker Compose (PHP 8.5 + `intl`, `bcmath`, PostgreSQL, Redis); `composer.json` php `^8.4`; `.env.example` → `pgsql` + `redis` (separate DBs); PostgreSQL test DB (`restano_test`). `laravel/fortify` moves to Phase 4, `alpinejs` to Phase 5 | `docker exec restano-php-fpm-1 php artisan test` green on PostgreSQL |
| P. Panels | Filament 5 install; `admin` and `app` panels with login and dashboards on the central domain; `PlatformRole` + `users.platform_role`; `TenantContext`, `BelongsToTenant`; tenants, translations, memberships, locations, `location_user`; `ResolveTenantFromMembership`; branch authorization | Panel, isolation and authorization tests pass |
| 1. Tenancy core | Remainder after Phase P: resolver pipeline, public middleware, queue propagation, `tenant_locales` | Isolation, scope and queue tests pass |
| 2. Database foundation | Remaining migrations with composite FKs, enums, models, `HasTranslations`, factories | DB rejects cross-tenant inserts; relationship tests pass |
| 3. Subscriptions | Plans, prices, limits, `SubscriptionService`, lifecycle command, `FeatureGate`, `PlanSeeder` | Lifecycle and grace tests pass |
| 4. Authentication | Fortify (registration, password reset, email verification), Blade auth views (tr/en), `TenantRegistrationService`, `/account`; `GET /login` → `/app/login` | Registration atomicity and auth tests pass |
| 5. Marketing site | `components/ui` design system, marketing layout and pages, pricing from DB, tr/en, SEO | Pages render in both locales |
| 6. Public menu infrastructure | Snapshot builder, cache versions, `PublicMenuService`, routes, `/q/{code}`, unavailable page, `DemoTenantSeeder`, reference theme `classic` | Access, visibility, cache, query-count and QR tests pass |
| 7. Themes | `minimal`, `bistro`, `cafe`, `grid`; theme showcase | Theme architecture and zero-query tests pass for all five |
| 8. Hardening | CSP and security headers, HTTP caching/ETag, image upload pipeline, rate limits, performance pass | Security checklist and performance budget met |

**Environment status** (2026-11-15): Node 24 LTS runs in the on-demand `node` Compose service; Postgres data is on the named volume `restano_postgres-data`; Alpine.js is not installed yet (Phase 5).

## Open decisions

Working defaults apply until each item is confirmed.

| Decision | Working default |
|---|---|
| Plan names, TRY prices, limits | Placeholder seeds |
| Pricing shown KDV-inclusive or "+KDV" | Display-only decision; storage unaffected |
| Trial / grace length | 14 days / 7 days |
| When email verification is required | To be decided |
| QR image generation library | `chillerlan/php-qrcode` is now installed as a mandatory Filament dependency; using it directly for QR images still needs a decision |
| Per-location price overrides | Deferred (`location_item_states` can take `price_override` later) |
| Marketing locales | `tr` at `/`, `en` at `/en` |
