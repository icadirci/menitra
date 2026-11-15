# Backend Development Rules

## General
- Use Laravel 12 conventions and PHP 8.4+ features.
- Follow PSR-12 coding standards; use `declare(strict_types=1);`.
- Use dependency injection.
- Keep controllers thin: validate (Form Request) → call a service → return a response.
- Use Form Requests for request validation.
- Use Eloquent relationships.
- Avoid business logic in Blade templates.
- Use service classes for complex business operations; do not create a service for trivial operations.
- Use database transactions where necessary.
- Use Laravel Policies for authorization.
- Use descriptive class and method names.
- Avoid unnecessary repository patterns.
- Prefer Laravel native functionality.
- Write feature tests for critical business flows.
- Do not introduce additional dependencies without approval.

## Tenancy
- Never bypass tenant isolation.
- Obtain the current tenant only from `App\Tenancy\TenantContext`; never resolve tenants inside controllers, Filament pages or resources. `Filament::getTenant()` is used only by the middleware that fills `TenantContext`.
- Tenant-owned models use the `BelongsToTenant` trait; `tenant_id` is never mass-assignable and is set from the context.
- Scope bypass (`withoutTenantScope()`) is allowed only in tenant resolvers, `QrCodeResolver` and commands/jobs marked `TenantAgnostic`.
- Cross-tenant work (e.g. subscription lifecycle) loops over tenants and dispatches one tenant-scoped job per tenant.
- Listeners, observers and dispatched jobs that depend on written data use `afterCommit`.
- Jobs get the tenant automatically from the dispatching context (`TenantQueueListener`); never pass a raw tenant id to a job to switch tenants.

## Panels and authorization
- Filament panels are presentation only: resources and pages call services and policies, and never hold business logic.
- Panel access is decided in `User::canAccessPanel()`: `admin` requires a `platform_role`; `app` requires an active membership in an active tenant. Tenant access is decided in `User::canAccessTenant()` and re-checked by `ResolveTenantFromMembership`.
- Do not rely on Filament's tenant ownership scoping for isolation; `BelongsToTenant` and policies are the enforcement.
- Branch-level checks go through `TenantMembership::canAccessLocation()` / `accessibleLocations()`; owners reach every branch of their tenant, other roles only branches assigned in `location_user`.
- Menu content: owners always; a manager only when the menu is attached to at least one location and every attached location is assigned to them. Attaching/detaching menus, extra groups and tenant-wide settings (`TenantPolicy::manageSettings`) are owner-only.
- Translatable fields in the panel use `TranslatableFields`; services save them with `TranslationWriter` (enabled locales only, default locale required, other locales' rows untouched).
- Money in forms uses `MoneyInput` / `App\Support\Money` (string parsing, never floats).
- Do not add a global `Gate::before` that grants platform roles business permissions; platform and business authorization stay separate.
- `platform_role`, `tenant_user.role` and membership state are never mass-assignable from request input.

## Domain conventions
- Store money as integers in minor units (kuruş); format with a `Money` value object, never floats.
- Use PHP enums for fixed sets (roles — `PlatformRole`, `TenantRole` —, statuses, menu types, themes, allergens, dietary labels, plan features, billing intervals).
- Customer-facing text lives in translation tables and is read through `HasTranslations`; UI strings use `__()` with `lang/{locale}` files.
- Public menu URLs are generated only by `MenuUrlGenerator`.
- Theme choice, `theme_settings` and the branch theme override are written only through `ThemeService`; settings are validated with `MenuTheme::validator()`.
- Subscription state changes go only through `SubscriptionService` and are recorded in `subscription_events`.
- Plan restrictions are checked through `FeatureGate`, not ad-hoc comparisons, and **always inside the backend service that creates the resource** (`ensureCanCreate()`); a disabled panel button is only information. `FeatureGate` restricts creation, never viewing, editing or deleting existing records.
- Registration logic lives in `TenantRegistrationService`; Fortify actions only validate and delegate to it.
- Fortify routes are declared explicitly in `routes/auth.php` (`Fortify::ignoreRoutes()`); keep Fortify's controllers and route names.
