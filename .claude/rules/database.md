# Database Development Rules

## General
- PostgreSQL is the primary database (tests also run on PostgreSQL).
- Use Laravel migrations for schema changes.
- Define foreign keys and indexes appropriately.
- Use Eloquent relationships.
- Prevent N+1 queries; use constrained eager loading.
- Use transactions for atomic business operations.
- Do not make destructive schema changes without explicit approval.
- Do not execute production database modifications without explicit approval.

## Tenant integrity
- Ensure tenant ownership for all tenant-specific records: tenant-owned tables have `tenant_id NOT NULL` and `UNIQUE (tenant_id, id)`.
- Tenant-owned children and pivots reference tenant-owned parents with composite foreign keys `(tenant_id, parent_id) → parent (tenant_id, id)`.
- Translation tables have no `tenant_id`; they reference exactly one parent row with `ON DELETE CASCADE`.
- Business identifiers that must be unique per tenant use composite unique constraints (e.g. `(tenant_id, slug)`).
- Never trust tenant IDs from client requests.
- Never expose cross-tenant data.

## Conventions
- Internal primary keys are `bigint`; entities exposed publicly by reference get a `public_id` ULID.
- Money columns are `bigint` minor units (kuruş) with currency on the tenant or price row.
- Translatable text exists only in `{entity}_translations` tables with `UNIQUE (parent_id, locale)`.
- Fixed value sets use PHP enums stored as strings.
- `jsonb` is allowed only where data is genuinely unstructured (theme settings, audit event meta).
- Use soft deletes on tenants, locations and menus; never purge tenant data automatically.

## Caching
- Use tenant-aware Redis cache keys built only by `MenuCacheKeys`.
- Define cache invalidation (tenant or location version bump) whenever related data changes.
