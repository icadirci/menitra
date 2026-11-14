# Security Development Rules

## General
- Validate all incoming data.
- Apply authentication and authorization checks.
- Enforce tenant isolation on every tenant-owned resource.
- Never trust client-side authorization or client-supplied tenant identifiers.
- Prevent mass assignment vulnerabilities.
- Use CSRF protection.
- Escape untrusted output.
- Validate uploaded files (MIME, extension, size); store under `tenants/{tenant_public_id}/…` with random names.
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

## Sessions and domains
- The session cookie is bound to the central domain only, never `.restano.app`, so tenant subdomains never receive auth cookies.
- Slugs are validated against the reserved list in `config/tenancy.php` and the slug format rule.

## Queues and cache
- Jobs run with the tenant context restored from the payload and cleared afterwards.
- Never cache user-specific or authorization-sensitive data in shared keys.
