# Menu System

> Status: **Approved — v0.4** (2026-11-16: implemented in Phase 6 — `menu` nullable for the location page, routing ids on summaries, minute in the ETag). v0.3 (2026-11-15: timed sold-out — `soldOutUntil` / `soldOutIndefinitely` in the snapshot, evaluated by `LiveMenuState`). v0.2: Covers locations, menus, menu content, QR codes, public URLs, locale handling and the rendering pipeline. The schema is in [database.md](database.md), themes in [themes.md](themes.md), caching in [caching.md](caching.md).

## Contents

- [Business structure](#business-structure)
- [Menu content model](#menu-content-model)
- [Public URLs](#public-urls)
- [Locale resolution](#locale-resolution)
- [QR codes](#qr-codes)
- [Rendering pipeline](#rendering-pipeline)
- [MenuSnapshot contract](#menusnapshot-contract)
- [Live state](#live-state)
- [Access rules and the unavailable page](#access-rules-and-the-unavailable-page)
- [Performance](#performance)
- [Extensibility](#extensibility)
- [Tests](#tests)

## Business structure

```
Tenant (business)
 ├── Locations (branches): address, contact, opening hours, special hours, timezone
 │     └── location_menu ──▶ Menus  (many-to-many; sort_order, is_active, optional serving hours)
 └── Menus (owned by the tenant): breakfast, lunch, dinner, beverages, …
       └── Categories → Items → Variants / Extra groups / Allergens / Dietary labels
```

- **Menus belong to the tenant, not to a location.** Locations *display* menus through `location_menu`. One breakfast menu can be shared by five branches without being duplicated. A branch that needs something different gets its own menu.
- **Content is shared; operational state belongs to each location:**

  | Shared on the menu | Per location |
  |---|---|
  | Categories, items, prices, variants, extras, allergens, labels, translations | Which menus are shown and in what order, sold-out status, opening hours, special hours, menu serving hours, theme override |

- Every tenant has exactly one default location (`is_default`). It is created at registration.

## Menu content model

| Concept | Rule |
|---|---|
| Menu type | `MenuType` enum, used for icons, default ordering and "now serving" hints |
| Visibility | `menus.is_published`, `menu_categories.is_visible`, `menu_items.is_visible`. Hidden content never enters the snapshot |
| Price | If an item has variants, it displays "from {lowest available variant price}" and lists the variants. Otherwise it displays `menu_items.price`. A null price with no variants means no price is shown, e.g. "ask staff" |
| Variants | Small / medium / large and similar. Each has its own price, availability and translations. At most one is the default |
| Extras | Reusable `extra_groups` (e.g. "Milk choice", "Extra shots") linked to items through `menu_item_extra_group`. A group has a selection type (single/multiple), `min_select`, `max_select` and `is_required`. Each extra has a `price_delta` |
| Allergens / dietary labels | `Allergen` and `DietaryLabel` enums stored in pivot tables. Names come from lang files |
| Featured / popular | Manual flags `is_featured` and `is_popular`. Later, "popular" can be computed from analytics without a schema change |
| Availability | `menu_items.is_available` is the item default. `location_item_states` overrides it for one branch: `is_sold_out` with an optional `sold_out_until` (UTC) — indefinitely sold out when it is null, sold out until that moment otherwise. Whether a timed sold-out has expired is decided at request time by `LiveMenuState`, never cached and never reset by a scheduler. Unavailable variants and extras are shown as unavailable, not hidden |

## Public URLs

| Form | Pattern |
|---|---|
| Subdomain (canonical in v1) | `{slug}.restano.app/` · `/{location}` · `/{location}/{menu}` |
| Path | `restano.app/m/{slug}/` · `/{location}` · `/{location}/{menu}` |
| Stable QR | `restano.app/q/{code}` |
| Custom domain (later) | `menu.example.com/{location}/{menu}` |

- `/` lists the locations. If the tenant has only one active location, it renders that location directly.
- `/{location}` is the location landing page: menus available at the branch, "now serving" highlight, address, hours and contact. If only one menu is attached, it renders that menu directly.
- `/{location}/{menu}` is the menu page.
- Both route groups (subdomain and path) point to the same controllers. Routes are defined in `routes/menu.php`.
- **Only `MenuUrlGenerator` builds public menu URLs.**

## Locale resolution

`ResolveMenuLocale` middleware:

1. If the `lang` query parameter is present **and** is one of the tenant's enabled locales, use it.
2. Otherwise use the tenant's default locale.

- The locale is part of the URL, not a cookie. Shared and HTTP caching therefore stays correct and needs no `Vary: Cookie`.
- The language switcher links to the same page with `?lang=xx`.
- Text falls back in this order: requested locale → tenant default → first available translation.

## QR codes

- **The URL is stable:** `restano.app/q/{code}`. `code` is 10 random Crockford base32 characters, globally unique and non-sequential.
- A QR code targets a location, and optionally a specific menu (deep link). `table_label` is reserved for future table service.
- **Redirect flow (`QrRedirectController` → `QrCodeResolver`):**
  1. Look up the code through the cache `qrmenu:qr:{code}`. This is a documented scope bypass, because the tenant isn't known yet.
  2. Unknown code → 404. Inactive code, or a tenant that's suspended or expired past grace → the unavailable page (without revealing which tenant).
  3. If the target menu is no longer attached to the location, fall back to the location page.
  4. **302** redirect to `MenuUrlGenerator->for($location, $menu)`. It's 302 rather than 301 so browsers never cache the target.
  5. Dispatch `QrCodeScanned` after the response. There is no listener in v1. Analytics later adds a listener and a `qr_scans` table, with no QR reprint.
- Because the target is computed at scan time, changing the slug, subdomain or a future custom domain never breaks printed codes.
- Registration creates one default QR code for the default location. QR **images** and the panel module come with B8 (`chillerlan/php-qrcode`).
- A code is normalised before lookup (case-insensitive; `I`/`L` → `1`, `O` → `0`, `U` → `V`). `/q/*` is limited by `throttle:qr` (60/min per IP); `QrCodeScanned` is dispatched with `defer()` after the response.

## Rendering pipeline

```
GET {slug}.restano.app/kadikoy/kahvalti?lang=en
 ① ResolveTenantFromRequest  → ResolvedTenant (cached)                     [tenancy.md]
 ② ResolveMenuLocale         → validated locale
 ③ MenuController@show       → thin; delegates
 ④ PublicMenuService
     ├─ MenuAccess: tenant active? now ≤ access_until? location active?
     │              menu published and attached to location?    ── no → MenuUnavailableException
     ├─ snapshot = MenuCache::remember(tenant, location, menu, locale,
     │                 fn () => MenuSnapshotBuilder::build(...))           [caching.md]
     └─ live     = LiveMenuState::for(snapshot->location, now())           (not cached)
 ⑤ ThemeRenderer → resolves theme (location override ?? tenant theme) + ThemeSettings
                 → view(theme->view(), ['menu' => snapshot, 'live' => live, 'theme' => settings])
```

- Controllers contain no business logic. Themes contain no business logic (see [themes.md](themes.md)).
- `MenuSnapshotBuilder` loads the whole tree in **one eager-loaded query set**. Constrained eager loads keep only visible rows, order them, and load translations for just the requested and default locales. Sold-out states for the location are loaded in one query.

## MenuSnapshot contract

`App\Support\Menu\Data\MenuSnapshot` is the **single data contract every theme consumes**. It's a tree of readonly DTOs containing only plain values:

```
MenuSnapshot
 ├─ version: string                (cache version, used for ETag)
 ├─ locale, availableLocales[]
 ├─ brand: TenantBrand             name, tagline, logoUrl, coverUrl, showPlatformBranding
 ├─ location: LocationInfo         name, address, phone, whatsapp, email, mapUrl, timezone,
 │                                 weeklyHours[], specialHours[] (next 30 days)
 ├─ menus: MenuSummary[]           for the menu switcher (name, type, url, servingHours[])
 │                                 (`id` on MenuSummary / LocationSummary is a routing key, never rendered)
 └─ menu: ?MenuData                name, description, type, featured: ItemData[]  (null on the location page)
      └─ categories: CategoryData[]   id, name, description, imageUrl
           └─ items: ItemData[]        publicId, name, description, imageUrl, price: ?Money,
                                       fromPrice: ?Money, isFeatured, isPopular,
                                       soldOutIndefinitely: bool, soldOutUntil: ?int (UTC epoch),
                                       calories, preparationMinutes,
                                       allergens: Allergen[], dietaryLabels: DietaryLabel[],
                                       variants: VariantData[], extraGroups: ExtraGroupData[]
```

- **Allow-listed:** only the fields above enter the snapshot. Internal IDs (other than display anchors), tenant status, subscription data, user data and hidden content never do.
- **The snapshot holds no time-dependent decision.** Sold-out data is stored as raw facts (`soldOutIndefinitely`, `soldOutUntil`); themes ask `$live->isSoldOut($item)` for the effective state. `isAvailable = false` (item default) is resolved in the snapshot because it doesn't depend on the clock.
- Text is already resolved for the locale. `Money` values carry a pre-formatted string (`₺85,00`).
- Plain data, not Eloquent models, is what gets cached. That keeps entries small, means no hidden attributes can leak, and makes entries independent of model changes.
- Changing the shape of the snapshot is an architectural change. All five themes and the contract tests must be updated together.

## Live state

`LiveMenuState` is computed **on every request** and never cached, because it depends on the clock:

- `isOpenNow` and `opensAt` / `closesAt`, in the location's timezone, taking special hours and midnight-crossing shifts into account
- the list of menus being served right now (from `location_menu_hours`)
- `isSoldOut(ItemData $item)`: `soldOutIndefinitely` or `soldOutUntil > now (UTC)`. A timed sold-out therefore ends exactly on time even while the snapshot stays cached, without a scheduler or a cache bump

It's computed from data already in the snapshot, with no database query.

## Access rules and the unavailable page

| Condition | Response |
|---|---|
| Unknown tenant slug, location, menu or QR code | 404 |
| Menu unpublished or not attached to the location, or location inactive | 404 |
| Tenant suspended, or subscription expired past grace | Unavailable page, **503**, no `Retry-After` |
| Inactive QR code | Unavailable page, 503 |

The unavailable page (`resources/views/menu/unavailable.blade.php`):
- is platform-branded and professional, in tr/en following the visitor's `lang` or the tenant default
- shows the business name if it's known
- is sent with `noindex` and `Cache-Control: no-store`
- is never cached under snapshot keys

The tenant's data is untouched.

## Performance

- With a warm cache, a menu request makes **zero database queries**: the tenant comes from the resolution cache, the versions come from one Redis `MGET`, and the snapshot is a single cache hit.
- HTTP: `ETag` built from `{snapshot version}.{locale}.{theme}.{UTC minute}`, with `Cache-Control: public, max-age=60`. The minute is included because live state (open now, timed sold-out) changes with the clock while the snapshot doesn't. A future CDN can serve unchanged menus.
- Menu routes (`routes/menu.php`) are registered without the `web` middleware group: no session, no cookies, no CSRF. On tenant hosts any non-GET method is a 404 (not 405).
- Item details open as an Alpine sheet over the rendered DOM (`menuPage` in `menu.js`), deep-linkable as `#i-{publicId}`; extras are display-only choices.
- Images use `loading="lazy"` with explicit dimensions. Above-the-fold cover images are eager.
- Assets on menu pages: `menu-base.css`, the theme stylesheet and `menu.js` (Alpine only). Search and the item modal run client-side over the already-rendered DOM, with no API calls.

## Extensibility

Future modules (ordering, table service, waiter calls, reservations) add new tables and services that read the same entities. They don't change existing structures:

- Items, variants and extras have stable `public_id`s.
- Extra groups already define `min_select`, `max_select` and `is_required`.
- `qr_codes.table_label` exists for table-bound QR codes.
- Prices are integer kuruş.
- The future dashboard writes through services and policies. Observers handle cache invalidation automatically.

## Tests

- Subdomain and path URLs render the same menu. `MenuUrlGenerator` produces the canonical URL.
- Hidden categories and items, unpublished menus, inactive locations and detached menus are not rendered.
- Sold-out at location A doesn't affect location B.
- Timed sold-out: sold out before `sold_out_until`, on sale after it with the same cached snapshot; indefinite sold-out stays until turned off.
- Variant "from" price and extras render correctly. Allergen and label names are translated.
- Locale: a valid `lang` is used; an invalid `lang` falls back to the tenant default; a missing translation falls back.
- Snapshot contains only allow-listed fields (serialised snapshot is checked against a forbidden-keys list).
- QR: follows a slug change; inactive code → 503; unknown → 404; detached menu → location fallback; `QrCodeScanned` is dispatched.
- Expired past grace → unavailable page with 503 and `no-store`.
- A warm request executes zero queries.
