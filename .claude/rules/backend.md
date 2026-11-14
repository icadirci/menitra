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
- Obtain the current tenant only from `App\Tenancy\TenantContext`; never resolve tenants inside controllers.
- Tenant-owned models use the `BelongsToTenant` trait; `tenant_id` is never mass-assignable and is set from the context.
- Scope bypass (`withoutTenantScope()`) is allowed only in tenant resolvers, `QrCodeResolver` and commands/jobs marked `TenantAgnostic`.
- Cross-tenant work (e.g. subscription lifecycle) loops over tenants and dispatches one tenant-scoped job per tenant.
- Listeners, observers and dispatched jobs that depend on written data use `afterCommit`.

## Domain conventions
- Store money as integers in minor units (kuruş); format with a `Money` value object, never floats.
- Use PHP enums for fixed sets (roles, statuses, menu types, themes, allergens, dietary labels, plan features, billing intervals).
- Customer-facing text lives in translation tables and is read through `HasTranslations`; UI strings use `__()` with `lang/{locale}` files.
- Public menu URLs are generated only by `MenuUrlGenerator`.
- Subscription state changes go only through `SubscriptionService` and are recorded in `subscription_events`.
- Plan restrictions are checked through `FeatureGate` / Gates, not ad-hoc comparisons.
- Registration logic lives in `TenantRegistrationService`; Fortify actions only delegate to it.
