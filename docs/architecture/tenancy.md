# Multi-Tenancy

> Status: **Approved — v0.2**. This is the authoritative tenant identification and isolation strategy.

## Contents

- [Tenant model](#tenant-model)
- [Isolation model](#isolation-model)
- [Core components](#core-components)
- [Tenant identification strategy](#tenant-identification-strategy)
- [Resolution flow](#resolution-flow)
- [Allowed scope bypasses](#allowed-scope-bypasses)
- [Queue processing](#queue-processing)
- [File storage](#file-storage)
- [Security controls](#security-controls)
- [Tests](#tests)

## Tenant model

- **One tenant = one business** (`tenants` table).
- A tenant has many **locations** (branches) and many **menus**. Menus are attached to locations through `location_menu` (see [menu-system.md](menu-system.md)).
- Users relate to tenants through `tenant_user` with a `role` (`owner`, `manager`, `staff`). A user may belong to several tenants. The current tenant for an authenticated session is chosen from that user's memberships.

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
| `App\Tenancy\TenantContext` | Scoped singleton (`$this->app->scoped()`) holding the current tenant. The **only** source of the current tenant. Reset per request and per job |
| `App\Tenancy\ResolvedTenant` | Cached value object: id, public_id, slug, status, default locale, enabled locales, theme, `access_until` |
| `App\Models\Concerns\BelongsToTenant` | Adds the global scope `where tenant_id = TenantContext::id()`, sets `tenant_id` on `creating`, keeps `tenant_id` out of `$fillable` |
| `App\Tenancy\TenantResolverPipeline` | Runs `TenantResolver` implementations in order |
| `ResolveTenantFromRequest` middleware | Public menu routes: runs the pipeline and sets the context, or returns 404 |
| `ResolveTenantFromUser` middleware | Authenticated routes: takes the tenant from the session's selected membership, re-validated on every request |
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
| Authenticated area | Session-selected membership of the logged-in user | v1 |
| QR code | `restano.app/q/{code}` → `qr_codes.tenant_id` → redirect | v1 |

Rules:

- **A tenant ID sent by the client is never trusted.** Tenants are identified only by host, path slug, QR code or authenticated membership.
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
  │                              ├─ /account          → ResolveTenantFromUser    │
  │                              └─ marketing routes  → no tenant                │
  │                                                                              ▼
  ├─ Host = {slug}.central ─────── SubdomainTenantResolver ───────────▶ TenantContext::set()
  │                                                                              ▲
  └─ Host = custom domain ──────── DomainTenantResolver (later) ─────────────────┘
```

- Lookups are cached as `qrmenu:host:{host}` and `qrmenu:slug:{slug}`, both holding a `ResolvedTenant`. With a warm cache, resolution needs zero queries. The entries are cleared when the slug, status, locales, theme or subscription changes (see [caching.md](caching.md)).
- A suspended tenant resolves, but `PublicMenuService` renders the unavailable page. An unknown slug or host returns 404.
- Marketing and auth routes are registered only for the central domain. Tenant subdomains serve only public menu routes.

## Allowed scope bypasses

`withoutTenantScope()` may appear only in the places below. Each one must have a test.

| Location | Reason |
|---|---|
| Tenant resolvers | The tenant isn't known yet |
| `QrCodeResolver` | The QR code identifies the tenant |
| `ResolveTenantFromUser` / membership lookups | The tenant is derived from the user |
| Commands and jobs implementing `TenantAgnostic` | Cross-tenant maintenance, e.g. subscription lifecycle |
| `TenantRegistrationService` | Creates the tenant, then sets the context for the remaining inserts |

## Queue processing

`SerializesModels` re-queries models when a job is **unserialized**, and that happens before job middleware runs. Restoring the context in job middleware is therefore too late, because the fail-closed scope would throw. The context is restored at the queue-event level instead:

1. `Queue::createPayloadUsing()` adds `tenant_id` to every job payload whenever a tenant context is active.
2. A `JobProcessing` listener fires *before* the command is unserialized. It loads the tenant, refuses suspended or deleted tenants (the job fails with a clear exception), and sets `TenantContext`.
3. `JobProcessed`, `JobFailed` and `JobExceptionOccurred` listeners **always** clear the context, so nothing leaks to the next job on the same worker.
4. Jobs and commands that span tenants implement the `TenantAgnostic` marker. They iterate over tenants and dispatch one tenant-scoped job per tenant, so a failure in one tenant doesn't affect the others.
5. Listeners, observers and jobs that depend on newly written data are dispatched `afterCommit`.

## File storage

- Path layout: `tenants/{tenant_public_id}/{type}/{uuid}.{ext}`, where type is `logo`, `cover`, `locations`, `categories` or `items`.
- Validate MIME type, extension and size. Store files under random names, and never use the user-provided filename in the path.
- Images are re-encoded and resized in Phase 8 (dependency approval pending).
- The disk is local in development; S3-compatible storage can be used in production. Paths are tenant-prefixed either way.

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
| Slug squatting / route collision | Reserved list and format rule |
| QR enumeration | Random 10-character Crockford base32 codes; `/q/*` rate limited; inactive codes don't reveal the tenant |
| Public data exposure | Allow-listed `MenuSnapshot` (see [menu-system.md](menu-system.md)) |
| Data loss | Soft deletes; tenant data is never purged automatically |

## Tests

- Tenant A cannot list, bind, update or delete tenant B's records.
- Querying a tenant-owned model without a context throws.
- Inserting a child row whose `tenant_id` differs from its parent's fails at the database level.
- A job dispatched under tenant A runs under tenant A, and the context is empty after it.
- Subdomain and path resolve the same tenant. An unknown slug gives 404. A reserved slug is rejected at registration.
- The session cookie isn't sent to tenant subdomains (configuration test).
