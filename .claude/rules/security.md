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
- `/admin` resources are authorized by their policies, not by panel access alone: plans (prices, limits) are super-admin only and never deleted; marketing FAQ and testimonials allow any platform role; site settings (public contact details, `SiteSettingPolicy`) are super-admin only, checked in `canAccess()` and again in `save()`. Social links must be `https://` URLs. Platform content is public by design, but only published / public records with allow-listed fields reach the marketing pages (`MarketingContent`).
- The `{tenant-slug}` in `/app/{tenant-slug}` is an identifier, not authorization: Filament checks `canAccessTenant()`, and `ResolveTenantFromMembership` re-verifies the membership before setting `TenantContext`. A tenant the user can't access returns 404.
- Branch staff and managers act only on branches assigned to their membership; owners on all branches of their tenant.
- QR codes (`QrCodePolicy`): every role views and downloads codes of its branches; owners and managers create and edit them for their branches (the submitted branch is re-checked, also when a code is moved); only owners delete. `QrCodeService` requires a deep-linked menu to be attached to the code's branch and never changes `code`.
- Themes (`ThemeService`): the tenant theme and `theme_settings` are owner-only (`TenantPolicy::manageSettings`); the branch override is owner-only (`LocationPolicy::setTheme`, not dehydrated for other roles, a submitted value from them is a 403). Settings are validated on the server (hex colours, curated `MenuFontPair`, allow-listed layout options, contrast) and normalised, so no tenant string reaches CSS; font stacks come only from the enum. Only built themes can be chosen and newly choosing a premium theme goes through `FeatureGate`.
- Panel registration is disabled; accounts are created by `TenantRegistrationService` (business), by accepting a team invitation, or explicitly by a super admin / artisan command (platform roles).
- Never secure a panel feature only by hiding its navigation item.
- Livewire endpoints serve panels only: the update route is bound to the central domain (the default host-agnostic route is disabled), and temporary upload/preview require the central host plus a user with panel access (`RestrictLivewireFileEndpoints`), on top of Livewire's signed URLs and throttling.
- `/account` (central domain, `auth` + `verified`, `no-store`, `noindex`, Blade only): the tenant comes only from the user's active membership (`ResolveAccountTenant`, context set after `membershipFor()`); request input never selects it. Subscription details are owner-only (`TenantPolicy::manageSettings`); other roles see only whether the menu is online and their own branches. QR downloads (`/account/qr/{code}.{svg|png}`) go through the tenant scope and `QrCodePolicy::view`.
- Members of a suspended tenant get an explicit 403 in `/app` and `TenantContext` is never set; non-members always get 404.
- Tenant-wide settings pages (profile, languages, theme, subscription) are owner-only through `TenantPolicy::manageSettings`, checked in `canAccess()` and again in every action.
- Public registration never accepts `platform_role`, `tenant_id` or `role`; only the validated fields reach `TenantRegistrationService`.
- There is a single login per panel (Filament). Fortify's own routes are ignored; do not add another `POST /login`. The central `POST /logout` exists only because the verification page can't reach the panel's logout.
- The business panel requires a verified email (`EnsureEmailIsVerified`, Livewire-persistent); the admin panel doesn't.
- `/admin` requires two-factor authentication (Filament MFA, TOTP + recovery codes): a platform user without MFA is sent to set it up and reaches nothing else. MFA secrets and recovery codes are stored with encrypted casts. In `/app` MFA is optional.

## Admin platform management (launch plan Faz 8)
- Every admin resource and action is authorized by its policy (`super_admin` everything; `platform_staff` only what its policy lists). Only super admins assign `platform_role`, soft-delete tenants or impersonate. No hard deletes of tenants or users.
- Cross-tenant reads only in the admin bypass area and under its rules (tenancy.md § Allowed scope bypasses): read-only, allow-listed models, explicit `tenant_id` constraint.
- Subscriptions are read-only in `/admin`; state changes go only through `SubscriptionService`.
- Admin actions that change state (suspend/unsuspend, slug change, soft delete, platform user creation, role assignment, resend verification, impersonation start/end) are written to the append-only `admin_audit_events` (actor, action, subject, tenant, meta, IP). Audit rows are never updated or deleted from the application.
- A slug change goes through `TenantSlugService` (format, reserved list, uniqueness) and clears the resolution cache.

## Impersonation
- Only a `super_admin`, only into a user **without** a platform role; never nested.
- The impersonator's id is stored in the server-side session, never in a client-controlled value; the impersonation ends automatically after 60 minutes.
- While impersonating: `/admin` is refused, and password, email and MFA changes are blocked (on the server, not just hidden).
- `/app` shows a persistent "viewing as X / leave" band. Leaving regenerates the session and restores the admin user; start and end are audited.
- The impersonated user is resolved through the normal `/app` membership checks; impersonation grants no extra permission inside the tenant.

## Team invitations (launch plan Faz 7)
- Only owners invite, resend, revoke, change roles and branch assignments, deactivate and remove members; checked in `canAccess()` and again in every action and service. Branches in an invitation or assignment are resolved through the tenant scope and stored with composite FKs.
- Invitation tokens are random (≥ 32 bytes of entropy), single-use, expire (default 7 days) and are stored only as a hash (`token_hash`); lookup compares in constant time. Accepted, revoked or expired tokens are rejected.
- An invalid or unknown token never reveals the tenant, the inviter or the invited email.
- An existing account accepts only when signed in with the invited email; otherwise an explicit error and no membership. A new account sets name and password with Fortify's password rules; the email counts as verified because the token proved it.
- `/invitations/{token}` is central-domain only and throttled. The invitation form never accepts `tenant_id`, `role` or branch ids; they come from the stored invitation.
- One owner per tenant; the owner can't remove or demote themselves; deactivated members lose access immediately. Invitations count against `max_members` (`FeatureGate::ensureCanCreate()`). Tenant names in the invitation mail are escaped.
- Marketing and auth routes are registered for the central domain only; a tenant subdomain's `/` is 404 until public menus exist.
- Queued jobs carry `tenant_id` in the payload and never inherit a context; jobs of missing, deleted or suspended tenants fail without running.

## Public forms
- The contact form validates through `ContactRequest`, is rate limited per IP (`throttle:contact`) and has a honeypot; a filled honeypot answers like a success and sends nothing.
- Contact messages are only mailed (queued), never stored. The mail renders visitor input escaped (HTML view + plain text, no Markdown) and uses it only as Reply-To, never as From; names with line breaks are rejected.

## Sessions and domains
- The session cookie is bound to the central domain only, never `.restano.app`, so tenant subdomains never receive auth cookies.
- Slugs are validated against the reserved list in `config/tenancy.php` and the slug format rule.

## Queues and cache
- Jobs run with the tenant context restored from the payload and cleared afterwards.
- Never cache user-specific or authorization-sensitive data in shared keys.
