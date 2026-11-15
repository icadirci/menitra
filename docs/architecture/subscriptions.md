# Subscriptions and Pricing

> Status: **Approved — v0.3** (2026-11-15: `max_qr_codes`, `FeatureGate` semantics for missing subscriptions, placeholder limits). v0.2: Provider-agnostic foundation. **No payment integration is implemented yet.** iyzico or PayTR will be added later behind the `PaymentGateway` contract.

## Contents

- [Scope](#scope)
- [Data model](#data-model)
- [Lifecycle](#lifecycle)
- [Public menu access](#public-menu-access)
- [Scheduled processing](#scheduled-processing)
- [Plan features and limits](#plan-features-and-limits)
- [Payment architecture](#payment-architecture)
- [Pricing page](#pricing-page)
- [Data retention](#data-retention)
- [Configuration](#configuration)
- [Tests](#tests)

## Scope

| Supported now | Later |
|---|---|
| Monthly and annual prices (TRY) | Payment gateway (iyzico / PayTR) |
| Free trials | Invoices and payment records |
| Cancellation at period end | Proration on plan change |
| Expiration and grace periods | Coupons |
| Plan-based feature restrictions | Per-plan grace overrides |

## Data model

Tables are defined in [database.md](database.md#subscriptions).

- `plans` + `plan_translations`: a plan's identity and its tr/en name and description.
- `plan_prices`: one row per plan, interval and currency. Amounts are in kuruş; the default currency is `TRY`.
- `plan_limits`: one row per plan and `PlanFeature`.
- `subscriptions`: a tenant's subscription history. **At most one live subscription per tenant**, enforced by a partial unique index on `tenant_id` where `ended_at IS NULL`.
- `subscription_events`: an append-only audit trail of every transition.

**Implemented so far (2026-11-15):** `plans`, `plan_translations`, `plan_limits`, `subscriptions` (without `plan_price_id`), `subscription_events`, `SubscriptionService::startTrial()` used by registration, and `FeatureGate` (Phase B1). `PlanSeeder` idempotently seeds a placeholder `starter` plan (`config('subscriptions.default_plan')`) with **development placeholder limits** — they are not a pricing decision. **Added 2026-11-15:** `plan_prices` (monthly/yearly in kuruş), `plans.is_featured`, `subscriptions.plan_price_id` (nullable, unused until checkout exists), and the `/admin` Plan resource (super admins) saving through `PlanService` (code immutable, every `PlanFeature` gets a limit row, cleared prices are deactivated, one featured plan). `?plan=` selection and the lifecycle command come with Phase 3 / Phase 5.

**New tenants:** registration always creates a `trialing` subscription for the default plan in the same transaction (`SubscriptionService::startTrial()`, with a `trial_started` event), so a new tenant's plan limits apply immediately. Registration fails as a whole if the default plan is missing.

## Lifecycle

```
 register ─▶ trialing ──(payment succeeds)──────▶ active ◀──(renewed)── past_due
               │                                   │  │                    ▲
               │ trial_ends_at passed, unpaid      │  │ renewal failed     │
               ▼                                   │  └────────────────────┘
             grace ◀──(period ended, not renewed ──┘       │ still unpaid at period end
               │       or cancel_at_period_end)            ▼
               │                                         grace
               │ grace_ends_at passed
               ▼
            expired ──(new subscription / payment)──▶ active (new subscription row)
```

| Status | Meaning | Public menu |
|---|---|---|
| `trialing` | Free trial until `trial_ends_at` | Online |
| `active` | Paid until `current_period_ends_at` | Online |
| `past_due` | Renewal payment failed, period not yet over | Online |
| `grace` | Trial or period ended without payment; online until `grace_ends_at` | Online, plus an `/account` warning |
| `expired` | Grace passed; `ended_at` set | **Offline: unavailable page** |

Rules:

- **All transitions go through `SubscriptionService`.** Each one runs in a transaction and writes a `subscription_events` row.
- **Cancellation** sets `cancel_at_period_end = true` and `canceled_at`. The subscription stays `active` until the period ends, then moves to `grace`. A cancellation can be reverted before the period ends (`cancel_reverted`).
- Entering `grace` sets `grace_ends_at = now + grace_days`.
- Re-subscribing after `expired` creates a **new** subscription row and keeps the history.
- Every transition clears the tenant's resolution cache entries, because they hold `access_until` (see [caching.md](caching.md)).

## Public menu access

```
access_until = match (status) {
    trialing  => trial_ends_at + grace_days,
    active,
    past_due  => current_period_ends_at + grace_days,
    grace     => grace_ends_at,
    expired   => null (no access),
}
menu is online  ⇔  tenant.status = active  AND  access_until ≠ null  AND  now ≤ access_until
```

- `access_until` is implemented by `Subscription::accessUntil()` (also shown on the read-only subscription page in `/app`). It is computed when the tenant is resolved and stored in the cached `ResolvedTenant`. Requests compare it to the current time with **no query**, so expiry takes effect on time even before the scheduled job runs.
- After access ends, the public menu renders the professional unavailable page (503, `no-store`, `noindex`); see [menu-system.md](menu-system.md#access-rules-and-the-unavailable-page). QR redirects lead to the same page.
- The tenant can still log in and see `/account` with the reactivation path.

## Scheduled processing

`subscriptions:process-lifecycle` (`ProcessSubscriptionLifecycle`) runs **hourly** from `routes/console.php`:

1. It is `TenantAgnostic`. It finds subscriptions with pending transitions:
   - trial ended
   - period ended
   - grace ended
2. It dispatches one tenant-scoped job per tenant, so failures are isolated and each job runs inside that tenant's context.
3. Each job calls `SubscriptionService`. Transitions are **idempotent**: re-running is safe and never double-logs.

## Plan features and limits

`App\Enums\PlanFeature`:

| Feature | Type | Meaning |
|---|---|---|
| `max_locations` | count | Number of branches |
| `max_menus` | count | Number of menus |
| `max_items` | count | Number of items across all menus |
| `max_languages` | count | Enabled tenant locales |
| `max_qr_codes` | count | Number of QR codes |
| `premium_themes` | boolean | Access to themes that require it |
| `custom_domain` | boolean | Custom domains (later phase) |
| `remove_branding` | boolean | Hide the "Powered by Restano" footer |
| `qr_analytics` | boolean | Scan analytics (later phase) |

- In `plan_limits.limit_value`, `null` means unlimited. Boolean features use `0` or `1`. A missing row means the feature isn't included.
- **Which plan applies:** the plan of the tenant's live subscription (`ended_at IS NULL`) while its status is `trialing`, `active`, `past_due` or `grace`. With no live subscription, or an `expired` one, every count feature allows nothing new and every boolean feature is off.
- **The gate only controls creation and feature access.** It never blocks viewing, editing or deleting existing records, and nothing ever locks or changes tenants in bulk because they lack a subscription.
- `App\Services\Subscription\FeatureGate` (scoped singleton, memoised per request/job) is the only place limits are evaluated:
  - `limit(Tenant $tenant, PlanFeature $feature): ?int` — `null` = unlimited; `0` when not included
  - `allows(Tenant $tenant, PlanFeature $feature, ?int $currentCount = null): bool` — boolean features: enabled; count features: `$currentCount < limit` (`$currentCount` defaults to `usage()`)
  - `usage(Tenant $tenant, PlanFeature $feature): int` — current count, implemented per feature as its table exists (soft-deleted rows don't count; restoring a row is a creation and is checked)
  - `ensureCanCreate(Tenant $tenant, PlanFeature $feature): void` — throws `PlanLimitExceededException` (translated message)
- The tenant must be the one in `TenantContext` (otherwise `CrossTenantWriteException`); no scope bypass is involved.
- **Enforcement lives in backend services** (`TenantLocaleService::enable()`, `LocationService::create()` / `restore()`, `MenuService::create()` / `restore()`, `MenuContentService::saveItem()`, `QrCodeService::create()`). Disabled buttons in the panel only inform the user.
- **A downgrade never deletes content.** Content over a limit stays stored:
  - premium themes fall back to `classic`
  - branding reappears
  - extra locales are still stored, but menus are served only in locales within the limit (the default first)
  - creating new resources beyond limits is blocked in the future dashboard

## Payment architecture

- `App\Contracts\PaymentGateway` is defined now, with no implementation. Expected operations:
  - create a checkout or subscription
  - cancel
  - handle a webhook
  - fetch status
- `subscriptions.provider` and `subscriptions.provider_reference` are reserved for the gateway's identifiers.
- The gateway adapter (iyzico or PayTR) will translate provider events into `SubscriptionService` calls (`activate`, `renew`, `markPastDue`). **The lifecycle logic stays provider-independent.**
- `payments` and `invoices` tables, and Turkish e-invoice (e-fatura / e-arşiv) requirements, are designed with the integration phase.

## Pricing page

- Routes: `/fiyatlar` (tr) and `/en/pricing` (en), route `marketing.{locale}.pricing`, `PricingController` → `MarketingContent` → `marketing/pricing.blade.php`. Central domain only; both paths are reserved tenant slugs.
- Active, public plans with an active monthly price are read through `MarketingContent::plans()` (DTO `PricingPlan`: localised name/description, monthly and yearly `Money`, yearly-per-month and saving % computed from stored prices, and the plan's `plan_limits` as `feature => limit_value`). Only these allow-listed fields reach the views. Caching as `qrmenu:plans:{locale}` is deferred until `MenuCacheKeys` exists (Phase 6); the queries are eager-loaded (`translations`, `prices`, `limits`) and small.
- Monthly/annual toggle (Alpine, `x-data="{ yearly: false }"`, an ARIA radiogroup). Without JS the monthly prices are shown. The toggle appears only when at least one plan has a yearly price; a plan without one keeps its monthly price with a "monthly only" note. The yearly view shows the yearly total, the per-month equivalent (`intdiv(yearly, 12)`) and the plan's saving.
- **The annual saving is computed from stored prices, never hard-coded:** `floor((12 × monthly − yearly) × 100 / (12 × monthly))`, shown only when positive. The toggle badge (`MarketingContent::yearlySaving()`) shows the largest saving — "save X%" when every yearly-priced plan saves the same, otherwise "save up to X%".
- Plan card bullet points (`PricingPlan::highlights()`) and the comparison table (`MarketingContent::comparison()`) are built only from `plan_limits`, in `PlanFeature` order: count features show the number or "unlimited" (`null`), boolean features a check or a dash; a missing row or `0` is "not included". A feature appears in the table only when at least one listed plan includes it, so features nobody offers (e.g. a reserved `custom_domain = 0`) are never advertised. Count features and boolean features are two row groups. These are display values; enforcement stays in `FeatureGate`.
- Billing FAQ: published `Faq` entries with placement `pricing` (`MarketingContent::faqs(FaqPlacement::Pricing)`); the section is hidden when there are none. Home page FAQs (placement `home`) are not shown here.
- Amounts are formatted with `NumberFormatter` (`Money::format()`, `tr_TR`), e.g. `₺1.299` (whole amounts without fraction). The English page uses the `en` locale with TRY. Limit numbers use the page locale (`1.000` / `1,000`).
- Whether prices are shown KDV-inclusive or "+KDV" is an open decision; it affects display only (the page currently states neither).
- CTA: `/register`. Plan selection at registration (`/register?plan={code}`) is **not implemented yet**: every trial starts on the default plan (`subscriptions.default_plan`), so the CTA carries no plan code and the trial length shown is `subscriptions.trial_days`.

## Data retention

- **Expiration never deletes data.** Menus, locations, QR codes and settings remain intact and come back online on reactivation.
- Automatic purging is forbidden until an explicit retention policy is approved and documented here.

## Configuration

`config/subscriptions.php`:

| Key | Default | Meaning |
|---|---|---|
| `default_plan` | (seeded plan code) | Plan used for registration trials |
| `trial_days` | 14 | Fallback when the plan doesn't define one |
| `grace_days` | 7 | Grace period length |
| `currency` | `TRY` | Default currency |

The 14-day trial and 7-day grace defaults are working values pending confirmation.

## Tests

- Registration starts a `trialing` subscription with a `trial_started` event.
- Trial end → `grace` → `expired` at the correct times, with the menu online during grace and offline after.
- Cancellation keeps the subscription active until the period ends, then moves it to grace; revert works.
- Re-subscribing after expiry creates a new row; the partial unique index prevents two live subscriptions.
- `access_until` is correct for each status; expiry takes effect while the cache is warm.
- The lifecycle command is idempotent and isolates per-tenant failures.
- `FeatureGate` handles unlimited, count limits, boolean features and missing rows; trialing / active / past_due / grace apply the plan, expired or no subscription allows no creation; a downgrade falls back to `classic` without deleting settings.
