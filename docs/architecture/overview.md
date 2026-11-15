# Architecture Overview

> Status: **Approved — v0.4** (2026-11-15: business panel Phase B, manager content rule, `FeatureGate` in B1). v0.3: Filament panels, roles. v0.2: 2026-11-14. Changes to architectural decisions must update this folder in the same change.

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
- [Design reference](#design-reference)
- [Open decisions](#open-decisions)

Related documents: [tenancy](tenancy.md) · [database](database.md) · [menu system](menu-system.md) · [themes](themes.md) · [caching](caching.md) · [subscriptions](subscriptions.md)

## Product scope

A multi-tenant SaaS platform for cafes, restaurants, bakeries and other hospitality businesses to publish digital menus reached through QR codes. The primary market is Turkey: the default currency is TRY and the initial languages are Turkish (`tr`) and English (`en`).

| In scope (current) | Out of scope (later, architecture must allow it) |
|---|---|
| Public marketing website, pricing page, contact page; `/admin` resources for its content: plans and prices, FAQ, testimonials, site settings (contact details) | Other CRUD modules in `/admin` (tenants, users, subscriptions) |
| Registration, forgot password, email verification, minimal `/account` page | Payment gateway integration (iyzico / PayTR) |
| Filament panel infrastructure: `/admin`, `/app/{tenant-slug}`, login, initial dashboards, roles | Team management in `/app` (invitations, staff CRUD, branch-assignment screens) |
| Business management modules in `/app` ([Phase B](#business-panel-phase-b)) | |
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
  6. Trial subscription for the default plan, plus a `trial_started` `subscription_events` entry. Every new tenant therefore starts with a live `trialing` subscription, so the plan limits apply from the first request (see [subscriptions.md § Plan features and limits](subscriptions.md#plan-features-and-limits))
  7. A `tenant_locales` row for the registration locale (= `tenants.default_locale`)

  Added to the same transaction when their tables exist: default `theme_settings`, the default QR code, and `?plan=` selection.
- After commit, `TenantRegistered` is fired.
- Registration fields: name, email, password, business name, slug (auto-suggested, editable).
- Rate limits: Filament login (5/min), registration (`registration`: 5/min per IP), password reset (`password-reset`: 5/min per email+IP, 20/min per IP), verification links and resends (6/min). The session is regenerated on login.
- `User` implements `MustVerifyEmail`, so registration sends a verification email. **The business panel requires a verified email** (`EnsureEmailIsVerified` in the `app` panel's auth middleware, Livewire-persistent); unverified users are redirected to `/email/verify`. The admin panel doesn't require it. A central `POST /logout` (Fortify controller) lets an unverified user leave the verification page.
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
| Look | Filament defaults; Restano logo, Forest primary, Stone gray | Filament defaults; Restano logo, Forest primary, Stone gray |
| Resources | Plans (`PlanPolicy`: super admins only; saved by `PlanService`; never deleted), FAQ and testimonials (`FaqPolicy`, `TestimonialPolicy`: any platform role); page **Site settings** (`/admin/site-settings`, `ManageSiteSettings`, `SiteSettingPolicy`: super admins only) | Phase B modules |

Authorization layers:

1. **Panel:** `User::canAccessPanel()` — `admin` needs `platform_role`; `app` needs an active membership. Access to both panels requires both grants.
2. **Tenant (app):** `User::canAccessTenant()` + `ResolveTenantFromMembership`, see [tenancy.md](tenancy.md#business-panel-tenancy-apptenant-slug).
3. **Branch (app):** `TenantMembership::canAccessLocation()` / `accessibleLocations()`; owners reach every location of the tenant, managers and branch staff only their `location_user` assignments.
4. **Action:** policies (e.g. `LocationPolicy`, `TenantPolicy`; in `/admin` `PlanPolicy`, `FaqPolicy`, `TestimonialPolicy`, which check only the platform role) combine role and branch access. Every page and resource also checks `canAccess()` / policies; hiding navigation is never authorization.
5. **Plan:** `FeatureGate`, evaluated inside the backend service that creates the resource (never only in the UI). See [subscriptions.md](subscriptions.md#plan-features-and-limits).

Business action matrix (each row is implemented with its module in Phase B):

| Action | owner | manager | branch_staff |
|---|---|---|---|
| Dashboard | all branches | assigned branches | assigned branches (limited) |
| View locations | all | assigned | assigned |
| Update location (details, hours, special hours) | all | assigned | — |
| Create / delete / restore locations | yes | — | — |
| Attach / detach menus to locations, menu order and serving hours per location | yes | — | — |
| Menu content (menus, categories, items, variants, allergens, labels) | yes | only menus attached to ≥ 1 location where **every** attached location is assigned to the manager | — (read-only views) |
| Create menus | yes | — | — |
| Extra groups | yes | — | — |
| Sold-out state per location | all | assigned | assigned |
| QR codes: view and download (SVG/PNG) | all | assigned locations | assigned locations |
| QR codes: create, edit (label, target menu, branch), activate / deactivate | yes | assigned locations (old and new branch) | — |
| QR codes: delete (breaks printed codes; deactivation is the reversible alternative) | yes | — | — |
| Tenant settings: profile, languages, theme, subscription | yes | — | — |
| Team: invitations, staff, branch assignment | later phase | — | — |
| Permanent deletion | — | — | — |

The manager content rule exists because menus are shared: editing a menu also changes it at every location it is attached to, so a manager may only edit menus that are fully inside their branches. Because only owners can attach menus to locations, a manager can't widen that scope.

Livewire's update, upload and preview endpoints are restricted to the central domain and to panel users; see [tenancy.md](tenancy.md#livewire-endpoints). Members of a suspended business get an explicit 403 in `/app` and keep their data.

Platform roles: `super_admin` holds every platform permission; `platform_staff` gets limited platform permissions defined per future resource. Platform roles grant nothing in `/app`; there is no global `Gate::before`. Platform roles are assigned with `php artisan platform:assign-role {email} {role}` until a management UI exists.

### Business panel (Phase B)

Navigation groups of the `app` panel (Filament hides empty groups, so each group appears with its first page):

```
Dashboard
Menu management   Menus (categories as a relation manager) · Items · Extra groups
Locations         Locations (hours, special days, attached menus) · Stock status · QR codes
Business          Business profile · Languages · Theme & appearance · Subscription (read-only)
```

- No global "current branch" selector: branch-bound pages filter by location, with options taken only from `accessibleLocations()`; a submitted `location_id` is re-checked on the server.
- Shared panel building blocks live in `app/Filament/App/Forms/`:
  - `TranslatableFields` — one tab per enabled tenant locale (default locale first and required), state path `translations.{locale}.{field}`; saved by services through `App\Services\Translations\TranslationWriter`, never with `->relationship()`.
  - `MoneyInput` — TL in the form, kuruş (`App\Support\Money`) in the database; no floats.
  - `TenantImageUpload` — `media` disk, tenant-prefixed UUID paths, JPEG/PNG/WebP only (see [tenancy.md § File storage](tenancy.md#file-storage)).
- Pages and resources are presentation: they call services (which enforce `FeatureGate`) and policies.

| Step | Scope |
|---|---|
| B0 | Scope and architecture documents |
| B1 | Panel foundation: navigation groups, `Money`, `TranslatableFields` + `TranslationWriter`, `media` disk + `HasTenantMedia`, `tenant_locales` + `TenantLocaleService`, `plan_limits` + `FeatureGate`, `TenantPolicy`, Languages page |
| B2 | Business profile, subscription page (read-only). **Done 2026-11-15:** `EditBusinessProfile` (name/tagline/description per enabled locale, logo + cover; slug and currency read-only — a slug change is a support task for now) and `ViewSubscription` (plan, status, dates, `Subscription::accessUntil()`, limits with usage from `FeatureGate`), both owner-only |
| B3 | Locations: resource, opening hours, special hours. **Done 2026-11-15:** `LocationResource` (list/create/view/edit; query limited to `accessibleLocations()`, so another branch is a 404), weekly hours as a repeater on the form, special days as a relation manager. Writes go through `LocationService` (plan limit on create and restore, translations, default-branch rules: exactly one default, which can't be deactivated or deleted; switching it is owner-only via `LocationPolicy::setDefault`) and `LocationHoursService` (server-side time, overlap and closed-day rules). Panel glue `App\Filament\App\Support\ServiceCall` maps service validation errors onto form fields and plan limits onto notifications. The location theme override comes with B9 |
| B4 | Menus, location attachment (`LocationMenu` pivot model), categories. **Done 2026-11-16:** `MenuResource` (list limited by `Menu::scopeEditableBy()`, the manager content rule), `CategoriesRelationManager`, `LocationsRelationManager` (owner-only, with serving hours); `MenuService` (plan limit on create/restore), `MenuPolicy` |
| B5 | Items, allergens, dietary labels. **Done 2026-11-16:** `MenuItemResource`, `MenuContentService` (`max_items` on create), `MenuItemPolicy` (follows the item's menu; the submitted category is re-authorized) |
| B6 | Variants, extra groups. **Done 2026-11-16:** `VariantsRelationManager` (one default per item), `ExtraGroupResource` + `ExtrasRelationManager` (owner-only, `ExtraGroupService`) |
| B7 | Branch operations: stock status (`location_item_states`), menu serving hours. **Done 2026-11-16:** `ManageStock` page for every role on its accessible branches (`LocationPolicy::manageStock`, `StockService`: for today / until a time / indefinitely); serving hours on the menu ↔ location attachment |
| B8 | QR codes (`chillerlan/php-qrcode`, SVG + PNG). **Done 2026-11-15:** `QrCodeResource` (list/create/edit; query limited to codes of `accessibleLocations()`, so another branch's code is a 404). Target branch from `accessibleLocations()`, optional deep-linked menu only among menus attached to that branch; the submitted branch is re-checked (`QrCodePolicy::manageAt`, also when a code is moved) and `QrCodeService` enforces `max_qr_codes` on create, the branch (current tenant, not deleted) and the menu attachment. The `code` never changes. `QrCodeImage` renders SVG and PNG on demand from the stable `/q/{code}` URL (nothing is stored); download actions for every role on its branches, SVG preview on the edit page. Deactivation takes effect at once (the `qrmenu:qr:{code}` entry is forgotten by `MenuCacheObserver`); delete is owner-only |
| B9 | Theme & appearance (`theme_settings`) |
| B10 | Dashboard widgets and onboarding checklist |
| later | Team management / invitations, menu duplication, bulk price updates |

## Public marketing website

- Central domain only. Marketing routes are not registered on tenant subdomains.
- `layouts/marketing.blade.php` with header, footer and mobile navigation (Alpine).
- Pages:
  - Home
  - Features (`/ozellikler` · `/en/features`): copy in `marketing.features_page`; it claims only what this architecture provides — design mock-up claims without a backend counterpart (PDF/print templates, table codes, extra languages/RTL, custom domains, automatic image compression, plan-specific perks) are left out and guarded by `FeaturesPageTest`
  - Pricing (`/fiyatlar` · `/en/pricing`, `PricingController`): plans, prices and limits from `/admin` through `MarketingContent`; monthly/yearly toggle (Alpine), comparison table from `PlanFeature` limits, FAQs with placement `pricing` — see [subscriptions.md § Pricing page](subscriptions.md#pricing-page). Nav, footer and the home page (hero button, "compare all plans" under the pricing preview) link here
  - Themes showcase (live demo menus from `DemoTenantSeeder`, no hard-coded menu data)
  - Contact (`/iletisim` · `/en/contact`, `ContactController`): design `Contact.dc.html`. `ContactRequest` (Form Request) validates name, email, subject (`ContactSubject` enum) and message; `ContactMessageSender` queues `ContactMessageMail` (Redis queue → `queue` worker → Mailpit in development) to the site settings contact email, falling back to `mail.from.address` while none is set. The mail is a platform job (no tenant context), written in the platform default locale, Reply-To the visitor, visitor text escaped (no Markdown). Messages are not stored. Spam protection: `throttle:contact` (per IP 3/minute and 10/hour; over the limit the visitor is sent back to the form with an error) and a honeypot field (`ContactRequest::HONEYPOT`) — a filled honeypot gets the normal success redirect but nothing is sent. Post/Redirect/Get with a one-shot `contact_sent` flash for the success state. The channel card (email, phone/WhatsApp, support hours, office) and the footer's contact details and social links come from the **site settings** (`site_settings`, edited in `/admin` by super admins) through `MarketingContent::contactDetails()` (allow-listed `ContactDetails` DTO, memoized per request; the footer receives it from a view composer); empty fields are hidden. Nav, footer ("Company" column), the home FAQ lead ("Talk to us") and the home CTA ("Talk to sales") link here
  - Privacy and Terms
- Locales: Turkish at `/`, English at `/en/…`, set by the `SetMarketingLocale` middleware. Route names are `marketing.{locale}.{page}` with a locale-independent page name; page paths are localised in `routes/web.php` (`$marketingPaths`, e.g. `ozellikler` ↔ `features`, `fiyatlar` ↔ `pricing`), so links, canonical and hreflang always go through `MarketingUrl::to($page)`. Every localised page path is also listed in `tenancy.reserved_slugs` (enforced by `FeaturesPageTest`). The nav marks the current page with `aria-current="page"`; sections without their own page yet link to home page anchors. All strings come from `lang/{locale}/marketing.php`. App locale `tr`, fallback `en`; Laravel's own strings are translated in `lang/tr/{validation,passwords,pagination,auth}.php` and `lang/tr.json`.
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
    Admin/                   Pages/ (Dashboard, ManageSiteSettings), Resources/ (Plans, Faqs, Testimonials)
    App/                     Pages/ (Dashboard, ManageLocales, EditBusinessProfile, ViewSubscription, …),
                             Resources/ (Locations, Menus, MenuItems, ExtraGroups), Widgets/,
                             Support/ (ServiceCall, PanelContext), Pages/ManageStock,
                             Forms/ (TranslatableFields, MoneyInput, TenantImageUpload)
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
    Concerns/                BelongsToTenant, HasTranslations, HasTenantMedia
    Translations/            TenantTranslation, LocationTranslation, MenuTranslation, …
    Tenant, TenantDomain, TenantLocale, TenantMembership, User, Location, LocationOpeningHour,
    LocationSpecialHour, LocationItemState, Menu, LocationMenu, LocationMenuHour,
    MenuCategory, MenuItem, MenuItemVariant, ExtraGroup, Extra, MenuItemAllergen,
    MenuItemDietaryLabel, QrCode, ThemeSetting, Plan, PlanPrice, PlanLimit,
    Subscription, SubscriptionEvent
  Observers/                 MenuContentObserver, LocationStateObserver, TenantObserver
  Policies/                  LocationPolicy, TenantPolicy, MenuPolicy, QrCodePolicy, …
  Providers/                 AppServiceProvider, FortifyServiceProvider, TenancyServiceProvider,
                             Filament/AdminPanelProvider, Filament/AppPanelProvider
  Services/
    Tenancy/                 TenantRegistrationService, TenantLocaleService, SlugService
    Subscription/            SubscriptionService, FeatureGate
    Translations/            TranslationWriter
    Media/                   MediaCleaner
    Menu/                    PublicMenuService, MenuSnapshotBuilder, MenuCache, MenuCacheKeys,
                             LiveMenuState, MenuUrlGenerator, ThemeRenderer, QrCodeResolver
    QrCodes/                 QrCodeService (panel writes), QrCodeImage (SVG/PNG)
  Support/
    Menu/Data/               MenuSnapshot, TenantBrand, LocationInfo, MenuData, CategoryData,
                             ItemData, VariantData, ExtraGroupData, ExtraData, ThemeSettings
    Media/                   TenantMediaPath
    Money.php
  Tenancy/
    TenantContext.php, ResolvedTenant.php, TenantResolverPipeline.php
    Resolvers/               TenantResolver (interface), SubdomainTenantResolver,
                             PathTenantResolver, (DomainTenantResolver — later)
    Queue/                   TenantQueueListener, TenantAgnostic (interface)
config/                      tenancy.php, locales.php, subscriptions.php, fortify.php
lang/{tr,en}/                auth.php, marketing.php, menu.php, validation.php
resources/
  css/                       app.css (public site), menu-base.css (menus, Tailwind source(none)),
                             themes/{classic,minimal,bistro,cafe,grid}.css (@reference menu-base)
  js/                        app.js (marketing + auth + Alpine), menu.js (Alpine only),
                             lib/alpine.js (single guarded Alpine starter)
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
| B. Business panel | `/app` modules, steps B0–B10 ([Business panel](#business-panel-phase-b)). Pulls forward from Phases 1–3: `tenant_locales`, the menu content migrations as each step needs them, `plan_limits` + `FeatureGate` | Per step: authorization matrix, isolation and limit tests pass |
| 1. Tenancy core | Remainder after Phases P and B: resolver pipeline, public middleware (queue propagation done 2026-11-15; `tenant_locales` moved to B1) | Isolation, scope and queue tests pass |
| 2. Database foundation | Migrations not already created by Phase B, with composite FKs, enums, models, factories | DB rejects cross-tenant inserts; relationship tests pass |
| 3. Subscriptions | Remainder: plan prices, lifecycle command (`plan_limits`, `FeatureGate` and `PlanSeeder` limits moved to B1) | Lifecycle and grace tests pass |
| 4. Authentication | Fortify (registration, password reset, email verification), Blade auth views (tr/en), `TenantRegistrationService`, `/account`; `GET /login` → `/app/login` | Registration atomicity and auth tests pass |
| 5. Marketing site | `components/ui` design system, marketing layout and pages, pricing from DB, tr/en, SEO. **Status (2026-11-15):** design system, auth pages, marketing layout, home page (`/` tr, `/en` en; routes `marketing.{locale}.{page}`; content from `MarketingContent`) features page (`/ozellikler`, `/en/features`) pricing page (`/fiyatlar`, `/en/pricing`) and contact page (`/iletisim`, `/en/contact`, with `/admin` site settings) done. Design source: `docs/design/source/` (see [Design reference](#design-reference)) | Pages render in both locales |
| 6. Public menu infrastructure | Snapshot builder, cache versions, `PublicMenuService`, routes, `/q/{code}`, unavailable page, `DemoTenantSeeder`, reference theme `classic`. **Done 2026-11-16:** pulled forward the resolver pipeline (Phase 1) and the `qr_codes` table (B8); demo data is `DemoSeeder` (platform users, `kahve-konagi`, suspended `eski-firin`, non-public `demo` plan); `classic` follows `MenuKonak` / `DetailKonak` with self-hosted Young Serif + Figtree | Access, visibility, cache, query-count and QR tests pass |
| 7. Themes | `minimal`, `bistro`, `cafe`, `grid`; theme showcase | Theme architecture and zero-query tests pass for all five |
| 8. Hardening | CSP and security headers, HTTP caching/ETag, image upload pipeline, rate limits, performance pass | Security checklist and performance budget met |

**Environment status** (2026-11-15): Node 24 LTS runs in the on-demand `node` Compose service; Postgres data is on the named volume `restano_postgres-data`; `queue` (Redis worker), `scheduler` and `mailpit` services run with Compose; Alpine.js is installed (public site and menus only).

## Design reference

The approved visual design (2026-11-15) is kept as source files in `docs/design/source/` (`*.dc.html` artboards from the design canvas: marketing pages, auth pages, five menu identities with product detail views, and `DesignSystem.dc.html`). They are reference material, not code: views are rebuilt in Blade + Tailwind and never load these files.

- **Brand:** the product name stays **Restano**; the design's "Masa" name is not used. Its visual language (Forest `#1F4D3B`, Saffron `#E7A33E`, Paper/Ink neutrals, Bricolage Grotesque + Inter, radii 6/12/14/20/28) is implemented as tokens in `resources/css/app.css` and components in `resources/views/components/ui/`.
- **Fonts** are self-hosted from `resources/fonts/` (OFL, latin + latin-ext); no third-party font CDN on public pages. Menu theme fonts follow the same rule when the themes are built.
- **Panels** keep Filament's native design system; only the panel colours (`primary` Forest, `gray` Stone), the brand logo (`resources/views/filament/brand-logo.blade.php`) and the favicon are set in the panel providers. No custom Filament theme.
- **Menu themes** map onto the existing `MenuTheme` keys ([themes.md § Theme registry](themes.md#theme-registry)).
- Design elements without a backend counterpart (social sign-up, terms checkbox, password strength meter) are not implemented until requested.

## Open decisions

Working defaults apply until each item is confirmed.

| Decision | Working default |
|---|---|
| Plan names, TRY prices, limits | Placeholder seeds; the `starter` limits in `PlanSeeder` are development placeholders, not a pricing decision |
| Pricing shown KDV-inclusive or "+KDV" | Display-only decision; storage unaffected |
| Trial / grace length | 14 days / 7 days |
| QR image generation library | **Decided 2026-11-15:** `chillerlan/php-qrcode` (already installed with Filament) directly, SVG + PNG; no other QR package. Images are rendered on demand by `QrCodeImage` and not stored: they encode only the stable `/q/{code}` URL, so they never change |
| Production media storage | `media` disk is S3-ready; `league/flysystem-aws-s3-v3` is not installed and needs approval when production storage is set up |
| Per-location price overrides | Deferred (`location_item_states` can take `price_override` later) |
| Marketing locales | `tr` at `/`, `en` at `/en` |
