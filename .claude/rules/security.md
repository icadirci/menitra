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
- Public registration never accepts `platform_role`, `tenant_id` or `role`; only the validated fields reach `TenantRegistrationService`. Registration and invitation sign-up require the terms checkbox (`accepted`); `terms_accepted_at` / `terms_version` are set only by `User::recordTermsAcceptance()` and are never mass-assignable.
- There is a single login per panel (Filament). Fortify's own routes are ignored; do not add another `POST /login`. The central `POST /logout` exists only because the verification page can't reach the panel's logout.
- The business panel requires a verified email (`EnsureEmailIsVerified`, Livewire-persistent); the admin panel doesn't.
- `/admin` requires two-factor authentication (Filament MFA, TOTP + recovery codes): a platform user without MFA is sent to set it up and reaches nothing else. MFA secrets and recovery codes are stored with encrypted casts (recovery codes hashed first) and hidden from serialization; they are never mass-assignable. In `/app` MFA is optional. Both panels must use the same providers (`App\Filament\Shared\Auth\MultiFactor`), otherwise the weaker login opens the other panel through the shared session. The panel profile page manages MFA only (`EditProfile`: empty form, `save()` refused). The only out-of-band reset is `php artisan platform:reset-two-factor {email}`.

## Admin platform management (launch plan Faz 8)
- Every admin resource and action is authorized by its policy (`TenantPolicy` admin abilities, `UserPolicy`, `AdminAuditEventPolicy`) and again in the service (`TenantAdminService`, `UserAdminService`, `Impersonation`). `super_admin` everything; `platform_staff` only lists/views tenants, users and the audit log and resends verification mails. Only super admins suspend, change slugs, soft-delete/restore tenants, create platform users, assign `platform_role`, deactivate users or impersonate. Nobody changes their own role, deactivates or impersonates themselves. No hard deletes of tenants or users.
- Cross-tenant reads only in the admin bypass area and under its rules (tenancy.md § Allowed scope bypasses): read-only, allow-listed models, explicit `tenant_id` constraint.
- Subscriptions are read-only in `/admin`; state changes go only through `SubscriptionService`.
- Admin actions that change state (suspend/unsuspend, slug change, soft delete, platform user creation, role assignment, resend verification, impersonation start/end) are written to the append-only `admin_audit_events` (actor, action, subject, tenant, meta, IP). Audit rows are never updated or deleted from the application.
- A slug change goes through `TenantSlugService` (format, reserved list, uniqueness among tenants and other tenants' former slugs) and clears the resolution cache. The old slug is kept in `tenant_slug_redirects`: it 301-redirects to the current slug and can't be taken by anyone else (registration checks it too), so an old printed link can't be captured.
- Deactivated users (`users.deactivated_at`) reach no panel, are signed out on their next request (`LogoutDeactivatedUsers`, prioritised before authentication) and can't accept invitations.

## Impersonation
- Only a `super_admin`, only into a user **without** a platform role; never nested.
- The impersonator's id is stored in the server-side session, never in a client-controlled value; the impersonation ends automatically after 60 minutes (`EnforceImpersonation`, web group and both panels).
- While impersonating: `/admin` and invitation acceptance are refused, password reset routes stay guest-only, and MFA writes are refused by `EditProfile` and by `User::saveAppAuthentication*()` (on the server, not just hidden). There is no email change anywhere. A logout ends and audits the impersonation.
- `/app` shows a persistent "viewing as X / leave" band. Leaving regenerates the session and restores the admin user; start and end are audited.
- The impersonated user is resolved through the normal `/app` membership checks; impersonation grants no extra permission inside the tenant.

## Team invitations (launch plan Faz 7)
- Only owners invite, resend, revoke, change roles and branch assignments, deactivate and remove members; checked in `canAccess()` and again in every action and service. Branches in an invitation or assignment are resolved through the tenant scope and stored with composite FKs.
- Invitation tokens are random (≥ 32 bytes of entropy), single-use, expire (default 7 days) and are stored only as a hash (`token_hash`); lookup compares in constant time. Accepted, revoked or expired tokens are rejected.
- An invalid or unknown token never reveals the tenant, the inviter or the invited email.
- A signed-in user accepts only with the invited email; otherwise an explicit error and no membership. A signed-out existing account confirms its current password on the invitation page; this never starts a session (sign-in, with MFA, stays with the panel login). A new account sets name and password with Fortify's password rules; the email counts as verified because the token proved it.
- The invitation token is looked up only by `InvitationTokenResolver` (the documented scope bypass); unusable tokens (unknown, used, revoked, expired, suspended or deleted tenant) all get the same generic 404 page. The pages are `no-store`, `noindex` and send no referrer.
- `/invitations/{token}` is central-domain only and throttled (`throttle:invitations`: viewing per IP; accepting per IP and per token). The invitation form never accepts `tenant_id`, `role` or branch ids; they come from the stored invitation.
- One owner per tenant; the owner can't remove or demote themselves; deactivated members lose access immediately. Invitations count against `max_members` (`FeatureGate::ensureCanCreate()`). Tenant names in the invitation mail are escaped.
- Marketing and auth routes are registered for the central domain only; a tenant subdomain's `/` is 404 until public menus exist.
- Queued jobs carry `tenant_id` in the payload and never inherit a context; jobs of missing, deleted or suspended tenants fail without running.

## Payments (iyzico)
- Amounts, plans and periods are decided only on the server (`BillingCalculator`, `CheckoutService`); the checkout form submits a plan code and an interval, never an amount.
- Never trust the browser callback or the webhook payload: the token only finds the payment (`PaymentTokenResolver`, documented scope bypass), the result always comes from iyzico's retrieve API with a verified HMAC response signature, and `conversationId`, `basketId` and the paid amount must match the stored payment before `SubscriptionService` is called. A mismatch is never applied.
- Webhooks require a valid `X-IYZ-SIGNATURE-V3`; invalid ones get 403 and trigger nothing.
- `/billing/iyzico/*` runs without the `web` group (no session, no CSRF), is throttled and central-domain only. Never add session middleware there (it would replace the customer's session cookie).
- A payment is applied at most once (row lock, `succeeded` is final). Subscription changes go only through `SubscriptionService`.
- Only owners start checkouts and edit billing details (checked in `canAccess()` and in the services). Card data never reaches the app; only masked card info (association, family, last four) is stored. `tenant_billing_profiles.tax_id` and `payments.billing_details` are encrypted and hidden from serialization; `/admin` never shows them.
- Payment logs and Sentry get the payment's public id and tenant id only, never billing details or card data.
- Payment methods (`payment_settings`) are super-admin only (`PaymentSettingPolicy`, `canAccess()` + `save()`); a disabled method is refused in the checkout services, not only hidden. The IBAN is validated (`Iban`, mod-97) and the instructions are rendered escaped.
- Bank transfers: the reference is random (not an id), the transfer page is owner-only and read through the tenant scope; "I have paid" only notifies, it never applies anything. Only a super admin applies or rejects a transfer (`PaymentPolicy::review`, `PaymentAdminService`, in the payment's own tenant context, audited); applying goes through `PaymentProcessor` (row lock, at most once) and `SubscriptionService` / `ShopOrderService`. While a transfer is open no second payment for the same purpose starts. The notification mail carries no billing data.
- iyzico credentials only in `.env` (`IYZICO_*`); the SDK is used only inside `IyzicoGateway`, with timeouts (`IyzicoCurl`) and TLS verification on.

## QR stand shop
- Cart, checkout and orders are owner-only (`TenantPolicy::manageSettings`, also in `ShopCart` and `ShopCheckoutService`). QR codes are resolved through the tenant scope; composite FKs refuse another tenant's code.
- Products, prices and shop settings are super-admin only; platform staff only read orders; fulfilment is super-admin only (`ShopOrderPolicy::manage`), in the order's own tenant context, and audited.
- `shop_orders.delivery_address` is encrypted and hidden; it never enters mails, logs, Sentry or the audit log. Shown only to the owner and in the `/admin` order details.
- The registration form's `stands[...]` is visitor input: unknown products and invalid quantities are ignored, and the cart belongs to the tenant of the new user's own membership.
- Product images are stored only through `PlatformMedia` (`platform/shop/`); tracking links must be `https://`.

## Public forms
- The contact form validates through `ContactRequest`, is rate limited per IP (`throttle:contact`) and has a honeypot; a filled honeypot answers like a success and sends nothing.
- Contact messages are only mailed (queued), never stored. The mail renders visitor input escaped (HTML view + plain text, no Markdown) and uses it only as Reply-To, never as From; names with line breaks are rejected.

## Sessions and domains
- The session cookie is bound to the central domain only, never `.menitra.com`, so tenant subdomains never receive auth cookies.
- Slugs are validated against the reserved list in `config/tenancy.php` and the slug format rule.

## Queues and cache
- Jobs run with the tenant context restored from the payload and cleared afterwards.
- Never cache user-specific or authorization-sensitive data in shared keys.

## Monitoring
- Sentry receives errors only and never personal data: `config/sentry.php` sets `data_collection` explicitly (no user info or IP, no cookies, no request/response bodies, no query strings, allow-listed request headers, no SQL bindings, no stack frame variables, no job payloads); tracing, profiling, logs and metrics stay off. Don't loosen these or enable `send_default_pii`.
- URL paths that carry secrets (`/reset-password/{token}`, `/email/verify/{id}/{hash}`, `/invitations/{token}`) are redacted by `SentryEventScrubber`; a new route with a secret in its path is added there (and to `SentryPrivacyTest`).
- Never attach user, tenant content or request data to Sentry manually (`setUser`, `setContext`, `setExtra`); the internal `tenant_id` tag is the only tenant reference.
- Ops alert mails (`ops:health-check`) contain system state only, never tenant or user data.
