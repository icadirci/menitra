# Multi-Tenancy

> Status: **Approved — v0.4** (2026-11-15: `media` disk, upload ownership and cleanup). v0.3: business panel tenant URL strategy, branch roles. This is the authoritative tenant identification and isolation strategy.

## Contents

- [Tenant model](#tenant-model)
- [Isolation model](#isolation-model)
- [Core components](#core-components)
- [Tenant identification strategy](#tenant-identification-strategy)
- [Resolution flow](#resolution-flow)
- [Business panel tenancy](#business-panel-tenancy-apptenant-slug)
- [Allowed scope bypasses](#allowed-scope-bypasses)
- [Queue processing](#queue-processing)
- [File storage](#file-storage)
- [Security controls](#security-controls)
- [Tests](#tests)

## Tenant model

- **One tenant = one business** (`tenants` table).
- A tenant has many **locations** (branches) and many **menus**. Menus are attached to locations through `location_menu` (see [menu-system.md](menu-system.md)).
- Users relate to tenants through memberships (`tenant_user`, model `TenantMembership`) with a `role` (`owner`, `manager`, `branch_staff`) and an `is_active` flag. A user may belong to several tenants.
- Memberships are assigned to branches through `location_user`. Owners reach every location of their tenant; managers and branch staff reach only their assigned locations (see [database.md](database.md#platform-and-tenancy)).
- Platform staff (`users.platform_role`) are not tenant members. A platform role grants nothing inside `/app`; a person who needs both needs both grants.

## Isolation model

The data lives in **a single PostgreSQL database with a shared schema, and every tenant-owned row carries a `tenant_id` column.**

| Option | Decision |
|---|---|
| Database per tenant | Rejected: migration and connection cost at thousands of small tenants |
| Schema per tenant | Rejected: same operational cost |
| Shared schema + `tenant_id` | **Chosen** |

Tenancy is built in-house. No tenancy package is used.

Isolation is enforced in three layers:

1. **Application reads:** a fail-closed global scope (`BelongsToTenant`).
2. **Database writes:** composite foreign keys `(tenant_id, parent_id) → parent(tenant_id, id)`, so a cross-tenant relationship cannot be stored (see [database.md](database.md#tenant-integrity-constraints)).
3. **Authorization:** policies check both the tenant and the user's role.

PostgreSQL Row-Level Security is an optional fourth layer for a later phase.

## Core components

| Component | Responsibility |
|---|---|
| `App\Tenancy\TenantContext` | Scoped singleton (`$this->app->scoped()`) holding the current tenant. The **only** source of the current tenant. Reset per request and per job. It holds the `Tenant` model (decision 2026-11-15); whether a cached `ResolvedTenant` is needed is reassessed when public QR menu routing is built |
| `App\Tenancy\ResolvedTenant` | Cached value object of public resolution (id, slug, status, default locale, enabled locales, theme, saved `theme_settings` per theme (B9), `access_until`, names, `premium_themes` / `remove_branding` flags). **Introduced in Phase 6** (built by `ResolvedTenantFactory`): public menu requests use it instead of `TenantContext`, which `PublicMenuService` sets only while a snapshot is rebuilt — so a warm request needs no query. Plan-limit changes in `/admin` reach it within the resolution TTL (1h) |
| `App\Models\Concerns\BelongsToTenant` | Adds the global scope `where tenant_id = TenantContext::id()`, sets `tenant_id` on `creating`, keeps `tenant_id` out of `$fillable` |
| `App\Tenancy\Resolvers\TenantResolverPipeline` | Runs `SubdomainTenantResolver`, then `PathTenantResolver` (custom domains later) |
| `ResolveTenantFromRequest` middleware | Public menu routes: runs the pipeline and sets the context, or returns 404 |
| `ResolveTenantFromMembership` middleware | Business panel (`/app/{tenant-slug}`): runs after Filament's tenant identification, re-verifies the user's active membership in that tenant on every request (including Livewire updates) and only then sets `TenantContext`. Otherwise 404 |
| `App\Tenancy\Queue\TenantQueueListener` | Propagates and restores the tenant context for queued jobs |

### Fail-closed scope

If a model using `BelongsToTenant` is queried while `TenantContext` is empty, the scope throws `TenantNotResolvedException`. It never silently returns every tenant's rows. Bypassing it requires the explicit `withoutTenantScope()` call, and only in the [allowed places](#allowed-scope-bypasses).

Route model binding goes through the scope. A record that belongs to another tenant therefore gives **404**, not 403, and its existence isn't revealed.

## Tenant identification strategy

| Context | Mechanism | Phase |
|---|---|---|
| Central site | Host equals `config('tenancy.central_domain')` (`restano.app`) | v1 |
| Subdomain | `{slug}.restano.app` | v1 |
| Path | `restano.app/m/{slug}/…` | v1 |
| Custom domain | `tenant_domains.domain` (verified) | Later. The table exists now; `DomainTenantResolver` is a reserved slot |
| Business panel | `restano.app/app/{tenant-slug}` — slug in the URL, **verified against the user's active membership** (Filament tenancy) | v1 |
| `/account` | The logged-in user's membership (first active one until a selector exists): `ResolveAccountTenant` prefers active tenants, then the oldest membership, and sets `TenantContext` only after `membershipFor()` grants access. Request input is never used. Suspended-only → 403 page, no context; no membership → `/admin` for platform users, otherwise a 403 page | v1 (done 2026-11-15) |
| QR code | `restano.app/q/{code}` → `qr_codes.tenant_id` → redirect | v1 |

Rules:

- **A tenant ID sent by the client is never trusted.** Tenants are identified only by host, path slug, QR code or authenticated membership. A slug in a URL is only an identifier; in authenticated areas it is authorized against the membership before use.
- Slugs are lowercase, match `^[a-z0-9](-?[a-z0-9])*$`, are 3–40 characters long, are globally unique, and are not on the reserved list in `config/tenancy.php`: `www, app, api, admin, dashboard, account, q, m, mail, smtp, static, cdn, assets, img, docs, help, support, status, blog, billing, pricing, login, register`.
- A slug can change. Printed QR codes remain valid because `/q/{code}` computes its target at scan time.
- Public menu URLs are built only by `MenuUrlGenerator`. It chooses the tenant's canonical form: subdomain in v1, a verified primary custom domain later. Adding custom domains changes only this class and adds one resolver.

## Resolution flow

```
Request
  │
  ├─ Host = central domain ──────┬─ /q/{code}         → QrRedirectController (QrCodeResolver)
  │                              ├─ /m/{slug}/…       → PathTenantResolver ──────┐
  │                              ├─ Fortify routes    → central, no tenant       │
  │                              ├─ /admin/…          → admin panel, no tenant   │
  │                              ├─ /app/{slug}/…     → canAccessTenant ─▶ ResolveTenantFromMembership
  │                              ├─ /account          → ResolveAccountTenant     │
  │                              └─ marketing routes  → no tenant                │
  │                                                                              ▼
  ├─ Host = {slug}.central ─────── SubdomainTenantResolver ───────────▶ TenantContext::set()
  │                                                                              ▲
  └─ Host = custom domain ──────── DomainTenantResolver (later) ─────────────────┘
```

- Lookups are cached as `qrmenu:host:{host}` and `qrmenu:slug:{slug}`, both holding a `ResolvedTenant`. With a warm cache, resolution needs zero queries. The entries are cleared when the slug, status, locales, theme, theme settings or subscription changes (see [caching.md](caching.md)).
- A suspended tenant resolves, but `PublicMenuService` renders the unavailable page. An unknown slug or host returns 404.
- Marketing, auth and panel routes are registered only for the central domain. Tenant subdomains serve only public menu routes; until those exist, every path on a tenant subdomain (including `/`) is 404.

## Business panel tenancy (`/app/{tenant-slug}`)

Decision (2026-11-15): the business panel uses **Filament's built-in tenancy URL strategy**, which gives each business a stable URL and a tenant switcher for users with several memberships. This replaces the earlier "session-selected membership" plan for the panel.

Flow for every `/app/{tenant-slug}/…` request (and every Livewire update made from such a page):

1. Filament authenticates the user and checks `User::canAccessPanel('app')`: the user must have at least one active membership in an active, non-deleted tenant.
2. Filament loads the tenant by `slug` (soft-deleted tenants don't match) and calls `User::canAccessTenant($tenant)` (`isMemberOf`: active membership, any tenant status). Not a member → **404**, the tenant's existence isn't revealed.
3. `ResolveTenantFromMembership` (Filament persistent tenant middleware) re-verifies the membership itself:
   - not a member → **404**;
   - member of a **suspended** tenant → **403** with an explanation (`tenancy.suspended`); the context is never set and no data is touched;
   - otherwise `TenantContext::set()`.
   Suspension never deletes or changes tenant data; members can still log in and see why access is blocked.
4. From here on, `BelongsToTenant` scopes every tenant-owned query to the context. Filament's own tenant ownership scoping is treated as a convenience, **not** as the isolation layer.

`User::getTenants()` (the switcher) lists the non-deleted tenants in which the user has an active membership, including suspended ones (so their members reach the 403 page rather than a 404). `User::membershipFor()` — used by policies — returns a membership only for an **active** tenant, so a suspended tenant grants no permissions even if a context were set. Panel registration and Filament's tenant registration are disabled; tenants are created only by `TenantRegistrationService`.

### Livewire endpoints

Livewire is used only by the panels, so its endpoints follow the panels:

| Endpoint | Restriction |
|---|---|
| Update (`/livewire-{hash}/panels/update`) | Custom route bound to the central domain (`AppServiceProvider`). Livewire rejects its default host-agnostic update route with 404 once a custom one exists. On every update Filament's Livewire-persistent middleware re-runs `Authenticate` (`canAccessPanel`), `IdentifyTenant` (`canAccessTenant`) and `ResolveTenantFromMembership` |
| Temporary upload / preview | `RestrictLivewireFileEndpoints`: central host (else 404) and an authenticated user who can access at least one panel (else 403); then Livewire's signed URL (401) and throttling |
| JS assets | Static files, served on any host; contain no data |

## Allowed scope bypasses

`withoutTenantScope()` may appear only in the places below. Each one must have a test.

| Location | Reason |
|---|---|
| Tenant resolvers (`ResolvedTenantFactory`) | The tenant isn't known yet |
| `QrCodeResolver` | The QR code identifies the tenant |
| `ResolveTenantFromMembership` / membership lookups | The tenant is derived from the user's verified membership |
| Commands and jobs implementing `TenantAgnostic` | Cross-tenant maintenance, e.g. subscription lifecycle |
| `TenantRegistrationService` | Creates the tenant, then sets the context for the remaining inserts and restores the previous context afterwards |

## Queue processing

`SerializesModels` re-queries models when a job is **unserialized**, and that happens before job middleware runs. Restoring the context in job middleware is therefore too late, because the fail-closed scope would throw. The context is restored at the queue-event level instead:

1. `Queue::createPayloadUsing()` adds `tenant_id` to every job payload whenever a tenant context is active.
2. A `JobProcessing` listener fires *before* the command is unserialized. It loads the tenant, refuses suspended or deleted tenants (the job fails with a clear exception), and sets `TenantContext`.
3. `JobProcessed`, `JobFailed` and `JobExceptionOccurred` listeners **always** clear the context, so nothing leaks to the next job on the same worker.
4. Jobs and commands that span tenants implement the `TenantAgnostic` marker. They iterate over tenants and dispatch one tenant-scoped job per tenant, so a failure in one tenant doesn't affect the others.
5. Listeners, observers and jobs that depend on newly written data are dispatched `afterCommit`.

**Implementation (2026-11-15):** `App\Tenancy\Queue\TenantQueueListener`, registered in `TenancyServiceProvider`.
- `Queue::createPayloadUsing()` adds `tenant_id` only while a context is active.
- On `JobProcessing` the context is **cleared first** (a job never inherits one), then loaded from the payload. A missing, soft-deleted or suspended tenant marks the job failed (`TenantNotResolvedException`) and its `handle()` never runs. A job without `tenant_id` runs with an empty context, so any tenant-owned query throws.
- After the attempt (`JobProcessed`, `JobFailed`, `JobExceptionOccurred`, `JobAttempted`) the previous context is restored, idempotently per job UUID: empty in a worker, the request's own context for jobs run inline by the `sync` driver.
- The worker additionally resets scoped instances (and so `TenantContext`) between jobs.
- `App\Tenancy\TenantAgnostic` (marker interface, 2026-11-15) is implemented by `ProcessSubscriptionLifecycle` (`subscriptions:process-lifecycle`): its scope bypass only finds tenant ids; it sets each active tenant in `TenantContext` and dispatches a parameterless `ProcessTenantSubscriptionLifecycle` job, so the tenant reaches the job only through the payload.

## File storage

- **Disk:** `media` (`config/filesystems.php`). In development it is a local disk on `storage/app/public`, served through the `public/storage` link (`php artisan storage:link`, run only if the link doesn't exist), URL `APP_URL/storage`, visibility public. `MEDIA_DRIVER=s3` with `MEDIA_S3_*` switches it to S3-compatible storage in production; that needs `league/flysystem-aws-s3-v3` (not installed, approval required). The existing `public` disk is unchanged.
- **Path layout:** `tenants/{tenant_public_id}/{type}/{uuid}.{ext}`, where type is `logo`, `cover`, `locations`, `categories` or `items` (`App\Enums\MediaType`). Built only by `App\Support\Media\TenantMediaPath`. The file name is a UUID and the extension is derived from the detected MIME type; the client's filename and extension are never used.
- **Validation:** JPEG, PNG and WebP only; SVG and GIF are rejected. Three server-side checks in `TenantImageUpload`: Livewire's detected MIME type, the client extension, and the file's actual bytes sniffed with `finfo` (`TenantMediaPath::detectMimeType()`, also used to pick the stored extension). Size limits per `MediaType` (logo 2 MB, others 5 MB).
- **Ownership:** an existing file path in a Filament upload field is client-controllable Livewire state, so it is checked twice: `TenantImageUpload` uses Filament's `preventFilePathTampering()` (only the field's original path or a fresh upload is accepted), and models with images use `HasTenantMedia`, whose `saving` hook requires a changed path to lie inside the tenant's directory **for that media type** (`TenantMediaPath::isOwnedBy()`: prefix match, no `.`/`..` segments, no absolute paths, backslashes or NUL); otherwise `CrossTenantWriteException`.
- **Cleanup (`App\Services\Media\MediaCleaner`), never outside the tenant's prefix:**
  - Replaced or cleared image → the old file is deleted **after commit** (`DB::afterCommit`); a rollback keeps it.
  - Force-deleted record → its files are deleted after commit. Soft deletes keep files.
  - Newly stored upload → `MediaCleaner::store()` is the only way files are written; it registers the new file for deletion on **rollback** of every open transaction level (`afterRollBack`, so an outer rollback after a committed savepoint also counts). A failed save therefore never leaves an orphan. `TenantImageUpload` stores inside the save transaction (the `app` panel runs with `databaseTransactions()`). Rollback cleanup is tied to the moment a file is written, never to a model's `saving`, so a rolled-back save can't delete a file that another record still references.
  - Deletes are idempotent; failures are logged and never break the request.
- **Access:** menu images are public by nature. Isolation comes from unguessable UUID paths, ownership checks on every write and delete, and no directory listing.
- Images are re-encoded and resized in Phase 8 (dependency approval pending).

## Security controls

| Risk | Control |
|---|---|
| Cross-tenant read | Fail-closed global scope; route binding through the scope (404) |
| Cross-tenant write | Composite `(tenant_id, id)` foreign keys; services resolve related IDs through scoped queries |
| Mass assignment of `tenant_id` | Never fillable; set from `TenantContext` |
| Scope bypass misuse | Allowed only in the documented places; greppable; tested |
| Queue leakage | Payload-level tenant id; restored before unserialization; always cleared |
| Cache leakage | Keys built only by `MenuCacheKeys`, always containing the tenant id |
| File leakage | Tenant-prefixed paths, random names |
| Auth cookies on tenant subdomains | `SESSION_DOMAIN` is the central host only (never `.restano.app`) |
| `/account` tenant | Derived only from the user's active membership (`ResolveAccountTenant`); no tenant parameter exists and `?tenant=` is ignored; QR downloads are looked up through the tenant scope (404) and checked with `QrCodePolicy::view` |
| Tenant slug in `/app` URL | Identifier only: `canAccessTenant()` + `ResolveTenantFromMembership` verify the active membership; otherwise 404 |
| Panels on tenant hosts | `/admin`, `/app` and Livewire's update/upload/preview endpoints are bound to the central domain |
| Suspended tenant | Explicit 403 for members, 404 for others; no context, no permissions, data intact |
| Branch overreach | `location_user` composite FKs + `TenantMembership::canAccessLocation()` in policies |
| Slug squatting / route collision | Reserved list and format rule |
| QR enumeration | Random 10-character Crockford base32 codes; `/q/*` rate limited; inactive codes don't reveal the tenant |
| Public data exposure | Allow-listed `MenuSnapshot` (see [menu-system.md](menu-system.md)) |
| Data loss | Soft deletes; tenant data is never purged automatically |

## Tests

- Tenant A cannot list, bind, update or delete tenant B's records.
- Querying a tenant-owned model without a context throws.
- Inserting a child row whose `tenant_id` differs from its parent's fails at the database level.
- A job dispatched under tenant A runs under tenant A, and the context is empty after it; jobs never inherit a stale context; jobs of suspended, deleted or unknown tenants fail without running; inline `sync` jobs restore the request's context. *(TenantQueueTest)*
- Subdomain and path resolve the same tenant. An unknown slug gives 404. A reserved slug is rejected at registration.
- The session cookie isn't sent to tenant subdomains: config, raw `Set-Cookie` headers (no `Domain` attribute) and RFC 6265 domain matching. *(SessionCookieTest)*
- Central-only routes are 404 on tenant subdomains and unknown hosts. *(DomainIsolationTest)*
- `/app/{slug}` returns 404 for a user without an active membership in that tenant and for an inactive membership; members of a suspended tenant get 403; `TenantContext` stays empty in all three cases.
- Livewire updates are refused on tenant hosts and re-check panel and tenant access on every request.
- A member of tenant A sees only tenant A's locations in `/app/{a}`; branch staff see only assigned locations.
- `/admin` and `/app` aren't served on tenant subdomains.
