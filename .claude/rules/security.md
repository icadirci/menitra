# Security Development Rules

## General
- Validate all incoming data.
- Apply authentication and authorization checks.
- Enforce tenant isolation on every tenant-owned resource.
- Never trust client-side authorization or client-supplied tenant identifiers.
- Prevent mass assignment vulnerabilities.
- Use CSRF protection.
- Escape untrusted output.
- Validate uploaded files (MIME, extension, size); images are JPEG/PNG/WebP only (no SVG, no GIF). Store them on the `media` disk under `tenants/{tenant_public_id}/{type}/{uuid}.{ext}` built by `TenantMediaPath`, with the extension derived from the MIME type. Use `TenantImageUpload` (content sniffing, `preventFilePathTampering()`) + `HasTenantMedia`: a stored path must be inside the tenant's directory for its media type (existing paths in upload fields are client-controllable), files are written only through `MediaCleaner::store()` (removed on rollback), old files are deleted only after commit, and nothing outside the tenant prefix is ever deleted.
- Never expose secrets or credentials.
- Never commit environment files.
- Never disable middleware to bypass errors.
- Use secure password handling (Fortify, rate-limited login, registration and password reset).
- Protect sensitive routes with appropriate middleware.
- Test tenant isolation and authorization boundaries.

## Public menu and QR
- Public QR menu endpoints expose only intended public information: only allow-listed fields enter `MenuSnapshot`.
- Unpublished menus, hidden items, inactive locations and suspended or expired tenants never render menu content.
- Unknown slugs or QR codes return 404; inactive codes must not reveal which tenant they belong to.
- QR codes are random and non-sequential; `/q/*` is rate limited.
- The `lang` parameter is validated against the tenant's enabled locales before use.

## Panels
- `/admin` and `/app` are registered for the central domain only and never on tenant subdomains or custom domains.
- `/admin` access requires an explicit `platform_role`; `/app` requires an active membership in an active tenant. Holding one never implies the other.
- The `{tenant-slug}` in `/app/{tenant-slug}` is an identifier, not authorization: Filament checks `canAccessTenant()`, and `ResolveTenantFromMembership` re-verifies the membership before setting `TenantContext`. A tenant the user can't access returns 404.
- Branch staff and managers act only on branches assigned to their membership; owners on all branches of their tenant.
- Panel registration is disabled; accounts are created by `TenantRegistrationService` (business) or explicitly by a platform administrator / artisan command (platform roles).
- Never secure a panel feature only by hiding its navigation item.
- Livewire endpoints serve panels only: the update route is bound to the central domain (the default host-agnostic route is disabled), and temporary upload/preview require the central host plus a user with panel access (`RestrictLivewireFileEndpoints`), on top of Livewire's signed URLs and throttling.
- Members of a suspended tenant get an explicit 403 in `/app` and `TenantContext` is never set; non-members always get 404.
- Tenant-wide settings pages (profile, languages, theme, subscription) are owner-only through `TenantPolicy::manageSettings`, checked in `canAccess()` and again in every action.
- Public registration never accepts `platform_role`, `tenant_id` or `role`; only the validated fields reach `TenantRegistrationService`.
- There is a single login per panel (Filament). Fortify's own routes are ignored; do not add another `POST /login`. The central `POST /logout` exists only because the verification page can't reach the panel's logout.
- The business panel requires a verified email (`EnsureEmailIsVerified`, Livewire-persistent); the admin panel doesn't.
- Marketing and auth routes are registered for the central domain only; a tenant subdomain's `/` is 404 until public menus exist.
- Queued jobs carry `tenant_id` in the payload and never inherit a context; jobs of missing, deleted or suspended tenants fail without running.

## Sessions and domains
- The session cookie is bound to the central domain only, never `.restano.app`, so tenant subdomains never receive auth cookies.
- Slugs are validated against the reserved list in `config/tenancy.php` and the slug format rule.

## Queues and cache
- Jobs run with the tenant context restored from the payload and cleared afterwards.
- Never cache user-specific or authorization-sensitive data in shared keys.
