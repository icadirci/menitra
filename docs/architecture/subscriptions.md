# Subscriptions and Pricing

> Status: **Approved — v0.2**. Provider-agnostic foundation. **No payment integration is implemented yet.** iyzico or PayTR will be added later behind the `PaymentGateway` contract.

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

**Implemented so far (2026-11-15):** `plans`, `plan_translations`, `subscriptions` (without `plan_price_id`), `subscription_events`, and `SubscriptionService::startTrial()` used by registration. `PlanSeeder` seeds a placeholder `starter` plan (`config('subscriptions.default_plan')`). `?plan=` selection, prices, limits, the lifecycle command and `FeatureGate` come with Phase 3 / Phase 5.

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

- `access_until` is computed when the tenant is resolved and stored in the cached `ResolvedTenant`. Requests compare it to the current time with **no query**, so expiry takes effect on time even before the scheduled job runs.
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
| `premium_themes` | boolean | Access to themes that require it |
| `custom_domain` | boolean | Custom domains (later phase) |
| `remove_branding` | boolean | Hide the "Powered by Restano" footer |
| `qr_analytics` | boolean | Scan analytics (later phase) |

- In `plan_limits.limit_value`, `null` means unlimited. Boolean features use `0` or `1`. A missing row means the feature isn't included.
- `FeatureGate` is the only place limits are evaluated:
  - `FeatureGate::allows(Tenant $tenant, PlanFeature $feature, ?int $currentCount = null): bool`
  - `FeatureGate::limit(Tenant $tenant, PlanFeature $feature): ?int`
- It's exposed through Laravel Gates for the future dashboard and Form Requests.
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

- Active, public plans with their prices and limits are read through a small query service and cached as `qrmenu:plans:{locale}`.
- Monthly/annual toggle (Alpine). The annual saving is computed from stored prices, never hard-coded.
- Amounts are formatted with `NumberFormatter` (`tr_TR`), e.g. `₺1.299,00`. The English page uses the `en` locale with TRY.
- Whether prices are shown KDV-inclusive or "+KDV" is an open decision; it affects display only.
- CTA: `/register?plan={code}`. Registration validates the code and falls back to the default plan.

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
- `FeatureGate` handles unlimited, count limits, boolean features and missing rows; a downgrade falls back to `classic` without deleting settings.
