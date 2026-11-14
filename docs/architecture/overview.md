# Architecture Overview

> Status: **Approved — v0.2** (2026-11-14). Changes to architectural decisions must update this folder in the same change.

## Contents

- [Product scope](#product-scope)
- [Technology stack](#technology-stack)
- [System context](#system-context)
- [Application layering](#application-layering)
- [Request flows](#request-flows)
- [Authentication](#authentication)
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
| Public marketing website, pricing page | Admin dashboard, tenant management dashboard |
| Registration, login, forgot password, minimal `/account` page | Payment gateway integration (iyzico / PayTR) |
| Public QR menu pages, stable QR redirects | Online ordering, payment from the menu, waiter calling, reservations |
| Five menu themes | Custom domains, QR scan analytics |

## Technology stack

| Layer | Choice |
|---|---|
| Language / framework | PHP 8.4+, Laravel 12 |
| Database | PostgreSQL |
| Cache / queue / session | Redis (separate logical DBs: cache `1`, queue `2`, session `3`) |
| Authentication | Laravel Fortify (headless) with custom Blade views |
| Frontend | Blade (SSR), Tailwind CSS 4, Alpine.js, Vite |
| Tests | PHPUnit on a PostgreSQL test database |
| Local environment | Laravel Sail (PHP 8.4, PostgreSQL, Redis) |

Not used: React, Vue, Next, Nuxt, Inertia, Livewire, Bootstrap, jQuery, Laravel starter kits, tenancy packages, translation packages. Any new dependency requires approval.

## System context

```
                    ┌──────────────────────────────────────────────────────┐
 restano.app ──────▶│ Central context                                       │
                    │  • Marketing + pricing (tr at /, en at /en)            │
                    │  • Fortify auth, /account (tenant from user membership)│
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
| Policies / Gates | Authorization (prepared for the future dashboard) |
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

- Fortify features enabled: `registration`, `resetPasswords`, `emailVerification` (when verification is enforced is an open decision).
- Fortify views point to Blade templates in `resources/views/auth/` (tr/en). After login, users go to `/account`.
- `App\Actions\Fortify\CreateNewUser` validates the input and delegates to `TenantRegistrationService::register()`. That method runs **one database transaction** that creates:
  1. User
  2. Tenant (with a `tenant_translations` row for its name; slug validated and checked against the reserved list)
  3. `tenant_locales` (`tr` default, `en`)
  4. Owner membership (`tenant_user`, role `owner`)
  5. Default location (`is_default = true`)
  6. Default menu (type `all_day`), attached to the location via `location_menu`
  7. Default `theme_settings`
  8. Trial subscription: the plan from `?plan=` if it's valid, otherwise the default plan; plus a `subscription_events` entry
  9. Default QR code for the location
- After commit, `TenantRegistered` is fired.
- Registration fields: name, email, password, business name, slug (auto-suggested, editable).
- Login, registration and password reset are rate limited through Fortify's limiters. The session is regenerated on login.
- The session cookie is bound to the central domain only (see [tenancy.md](tenancy.md#security-controls)).
- `/account` is minimal and read-only: plan, trial/grace status, public menu link and QR link. **It is not a dashboard.**

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
  Enums/                     TenantRole, TenantStatus, MenuType, MenuTheme, Allergen, DietaryLabel,
                             ExtraSelectionType, SubscriptionStatus, SubscriptionEventType,
                             BillingInterval, PlanFeature
  Events/                    TenantRegistered, QrCodeScanned
  Exceptions/                TenantNotResolvedException, MenuUnavailableException
  Http/
    Controllers/
      Marketing/             HomeController, PricingController, ThemeShowcaseController,
                             ContactController, LegalController
      Account/               AccountController
      PublicMenu/            LocationIndexController, LocationController, MenuController,
                             QrRedirectController
    Middleware/              ResolveTenantFromRequest, ResolveTenantFromUser,
                             ResolveMenuLocale, SetMarketingLocale
    Requests/
  Models/
    Concerns/                BelongsToTenant, HasTranslations
    Translations/            TenantTranslation, LocationTranslation, MenuTranslation, …
    Tenant, TenantDomain, TenantLocale, User, Location, LocationOpeningHour,
    LocationSpecialHour, LocationItemState, Menu, LocationMenu, LocationMenuHour,
    MenuCategory, MenuItem, MenuItemVariant, ExtraGroup, Extra, MenuItemAllergen,
    MenuItemDietaryLabel, QrCode, ThemeSetting, Plan, PlanPrice, PlanLimit,
    Subscription, SubscriptionEvent
  Observers/                 MenuContentObserver, LocationStateObserver, TenantObserver
  Policies/                  Menu, Location, QrCode, … (for the future dashboard)
  Providers/                 AppServiceProvider, FortifyServiceProvider, TenancyServiceProvider
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
routes/                      web.php (central), menu.php (subdomain + /m groups), console.php
database/seeders/            PlanSeeder, DemoTenantSeeder, DatabaseSeeder
docs/architecture/           this folder
tests/
  Feature/                   Auth/, Registration/, Tenancy/, PublicMenu/, QrCode/,
                             Subscription/, Cache/, Marketing/
  Unit/                      Enums/, Services/, Support/
  Architecture/              ThemeIndependenceTest, CacheKeyTest
```

**Future dashboard:** it plugs in as `Http/Controllers/Dashboard/*` and `routes/dashboard.php`, reusing the existing services, policies and `FeatureGate`. `dashboard` and `app` are on the reserved slug list.

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
| Authorization | Policy matrix by role (prepared for the dashboard) |

## Implementation roadmap

Each phase ends with tests run, a summary of changed files, and a review pause.

| Phase | Scope | Exit criteria |
|---|---|---|
| 0. Environment | Sail (PHP 8.4, PostgreSQL, Redis); `composer.json` php `^8.4`; `.env.example` → `pgsql` + `redis` (separate DBs); PostgreSQL test DB; install `laravel/fortify` and `alpinejs` | `sail artisan test` green on PostgreSQL |
| 1. Tenancy core | `TenantContext`, fail-closed `BelongsToTenant`, resolver pipeline, middleware, queue propagation; tenants, users, `tenant_user`, `tenant_locales` | Isolation, scope and queue tests pass |
| 2. Database foundation | Remaining migrations with composite FKs, enums, models, `HasTranslations`, factories | DB rejects cross-tenant inserts; relationship tests pass |
| 3. Subscriptions | Plans, prices, limits, `SubscriptionService`, lifecycle command, `FeatureGate`, `PlanSeeder` | Lifecycle and grace tests pass |
| 4. Authentication | Fortify, Blade auth views (tr/en), `TenantRegistrationService`, `/account` | Registration atomicity and auth tests pass |
| 5. Marketing site | `components/ui` design system, marketing layout and pages, pricing from DB, tr/en, SEO | Pages render in both locales |
| 6. Public menu infrastructure | Snapshot builder, cache versions, `PublicMenuService`, routes, `/q/{code}`, unavailable page, `DemoTenantSeeder`, reference theme `classic` | Access, visibility, cache, query-count and QR tests pass |
| 7. Themes | `minimal`, `bistro`, `cafe`, `grid`; theme showcase | Theme architecture and zero-query tests pass for all five |
| 8. Hardening | CSP and security headers, HTTP caching/ETag, image upload pipeline, rate limits, performance pass | Security checklist and performance budget met |

**Known environment gaps** (as of 2026-11-14), fixed in Phase 0:
- The host PHP CLI is 8.2 and lacks the `pdo_pgsql` and `redis` extensions.
- `.env.example` uses MySQL and database drivers.
- Alpine.js is not installed.

## Open decisions

Working defaults apply until each item is confirmed.

| Decision | Working default |
|---|---|
| Plan names, TRY prices, limits | Placeholder seeds |
| Pricing shown KDV-inclusive or "+KDV" | Display-only decision; storage unaffected |
| Trial / grace length | 14 days / 7 days |
| When email verification is required | To be decided |
| QR image generation library | `chillerlan/php-qrcode` proposed, **not approved** |
| Per-location price overrides | Deferred (`location_item_states` can take `price_override` later) |
| Marketing locales | `tr` at `/`, `en` at `/en` |
