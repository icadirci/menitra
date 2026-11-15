# Caching Strategy

> Status: **Approved — v0.2**. Redis is the cache store. Public menu pages are the main caching target.

## Contents

- [Redis layout](#redis-layout)
- [Key catalogue](#key-catalogue)
- [Version-based invalidation](#version-based-invalidation)
- [Invalidation matrix](#invalidation-matrix)
- [What is never cached](#what-is-never-cached)
- [HTTP caching](#http-caching)
- [Rules](#rules)
- [Tests](#tests)

## Redis layout

| Purpose | Connection / DB | Notes |
|---|---|---|
| Cache | `cache` → DB 1 | Prefix `qrmenu:` for application keys |
| Queue | `default` → DB 2 | |
| Session | DB 3 | |

Separate databases mean `cache:clear` never wipes sessions or queued jobs.

## Key catalogue

All keys are built **only** by `App\Services\Menu\MenuCacheKeys`. Ad-hoc string building is not allowed.

| Key | Value | TTL |
|---|---|---|
| `qrmenu:host:{host}` | `ResolvedTenant` (subdomain or custom-domain lookup; includes the saved `theme_settings` of every theme) | 1h |
| `qrmenu:slug:{slug}` | `ResolvedTenant` (path lookup) | 1h |
| `qrmenu:qr:{code}` | `{tenant_id, location_id, menu_id, is_active}` | 1h |
| `qrmenu:tenant:{t}:version` | int, tenant content version | none |
| `qrmenu:tenant:{t}:location:{l}:version` | int, location version | none |
| `qrmenu:tenant:{t}:location:{l}:menu:{m}:{locale}:v{tv}.{lv}` | `MenuSnapshot` | 24h |
| `qrmenu:tenant:{t}:location:{l}:index:{locale}:v{tv}.{lv}` | Location landing data | 24h |
| `qrmenu:tenant:{t}:locations:{locale}:v{tv}` | Tenant location index (several branches) | 24h |
| `qrmenu:plans:{locale}` | Pricing page data | 6h |

`{t}`, `{l}` and `{m}` are internal numeric IDs. Every key built for tenant data contains the tenant ID.

## Version-based invalidation

Old entries are never deleted. Their **version changes** instead:

1. Snapshot keys embed both the tenant version `{tv}` and the location version `{lv}`.
2. A write bumps the relevant counter with `INCR`, which is atomic.
3. The next request reads the new versions, misses, rebuilds and stores under the new key.
4. Old keys become unreachable and expire through their TTL.

Reading both versions takes **one round trip** (`Cache::many([...])`) once the location is known; the tenant version is read first to find the location in the cached tenant location index. A missing counter reads as 1. TTLs live in `config/tenancy.php` (`cache.resolution_ttl`, `cache.snapshot_ttl`).

Why versions rather than deletes or tags:
- There's no race between deleting and rebuilding.
- One content change can affect many locations (shared menus). A version bump covers every one of them without tracking which locations use which menu.
- It works with any cache store, including `array` in tests.

**Why there's no per-menu version:** a per-menu version would require a reverse lookup (menu → locations) on every write. A content edit rebuilding a few snapshots for one tenant is cheap; correctness matters more.

## Invalidation matrix

Version bumps come from one model observer, `App\Observers\MenuCacheObserver` (registered in `TenancyServiceProvider` for every model in the matrix, translations included). `MenuCache` defers every bump and resolution clear with `DB::afterCommit`, so a bump never happens before the data is committed (and never after a rollback). Bulk query-builder writes fire no model events: services that use them (`LocationHoursService`) bump explicitly, and `TranslationWriter` deletes through models. Pivot changes go through the pivot models (`LocationMenu`) or explicit service calls, so observers always fire.

| Change | Bumps / clears | Effect |
|---|---|---|
| Menu, category, item, variant, extra group, extra, item–extra-group link, allergen, dietary label, or any of their translations | `tenant:{t}:version` | Every location of the tenant rebuilds |
| `location_menu` (attach, detach, order, active), `location_menu_hours` | `tenant:{t}:location:{l}:version` | Only that branch |
| `location_item_states` (sold-out, `sold_out_until`) | `tenant:{t}:location:{l}:version` | Only that branch. The *expiry* of a timed sold-out needs no bump: `LiveMenuState` compares `soldOutUntil` with the clock per request |
| Opening or special hours | `tenant:{t}:location:{l}:version` | Only that branch |
| Location details, location translations, location theme | `tenant:{t}:version` | Location names/addresses also appear in the tenant location index |
| Location created, deleted or (de)activated | `tenant:{t}:version` | Tenant location index |
| Tenant brand, translations, theme, theme settings, locales | `tenant:{t}:version` + `host:*`/`slug:*` entries for the tenant | Theme settings are carried in `ResolvedTenant`, so `ThemeSetting` writes clear the resolution entries too (`MenuCacheObserver`) |
| Tenant slug change | old and new `slug:` / `host:` entries + `tenant:{t}:version` | |
| Tenant status or subscription change | `host:` / `slug:` entries (they hold `access_until`) | Every `SubscriptionService` transition saves the `Subscription` model, so `MenuCacheObserver` clears them after commit |
| QR code change | `qr:{code}` | |
| Plan, price or limit change | `plans:*` | |

## What is never cached

- **Time-dependent state:** open now, serving now and whether a timed sold-out has expired (`LiveMenuState`), computed per request. The snapshot only carries the raw `soldOutUntil` timestamp.
- **Access decisions** outside `ResolvedTenant.access_until`. That value is a timestamp compared at request time, so expiry takes effect even if the cache is warm.
- The unavailable page, sent with `Cache-Control: no-store`.
- User-specific or authorization-sensitive data, which is never put under shared keys. The `/app` dashboard is not cached at all: its queries are few and constant, its rows depend on the member's branches and its stock and subscription state on the clock. If it ever needs caching, keys are built by `MenuCacheKeys` and include the tenant, the membership and the tenant version.
- Eloquent models. Only plain DTO data (`MenuSnapshot`, `ResolvedTenant`) is stored.

## HTTP caching

- Menu pages send `ETag: "{tv}.{lv}.{locale}.{theme}"` and `Cache-Control: public, max-age=60`. Requests with `If-None-Match` get a 304.
- Pages with a session or CSRF token (marketing forms, auth) are not marked public.
- Menu routes don't start a session. They run without `StartSession` and the cookie middleware, so they can be cached and never set cookies.

## Rules

- Every tenant-data key contains the tenant ID, and branch-specific keys also contain the location ID.
- Every write that affects public output must map to a row in the [invalidation matrix](#invalidation-matrix). New tables need a new row.
- Background jobs run inside the tenant context restored from the payload ([tenancy.md](tenancy.md#queue-processing)), so cache operations in jobs use the right keys.
- TTLs are set in `config/cache.php` or `config/tenancy.php`, never as magic numbers spread through the code.

## Tests

- A warm menu request executes zero DB queries.
- Editing an item bumps the tenant version, and both locations sharing the menu show the change.
- Marking an item sold-out at location A bumps only A's version; location B's snapshot is reused.
- Tenant A's changes never alter tenant B's versions or keys.
- `CacheKeyTest`: every `MenuCacheKeys` method that takes tenant data produces a key containing the tenant ID.
- A slug change invalidates the old slug entry; the QR redirect resolves the new URL.
- A rolled-back transaction does not bump versions (`afterCommit`).
- Saving theme settings bumps the tenant version and clears the slug entry; every location shows the new settings; a warm request with saved settings still runs zero queries (`ThemeManagementTest`).
