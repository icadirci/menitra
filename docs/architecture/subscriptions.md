# Subscriptions and Pricing

> Status: **Approved — v0.4** (2026-11-15: Phase 3 lifecycle — transitions, hourly command, grace anchored to the missed boundary, `/register?plan=`). v0.3: `max_qr_codes`, `FeatureGate` semantics for missing subscriptions, placeholder limits. v0.2: Provider-agnostic foundation. **No payment integration is implemented yet.** iyzico or PayTR will be added later behind the `PaymentGateway` contract.

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

**Implemented so far (2026-11-15):** `plans`, `plan_translations`, `plan_limits`, `subscriptions` (without `plan_price_id`), `subscription_events`, `SubscriptionService::startTrial()` used by registration, and `FeatureGate` (Phase B1). `PlanSeeder` idempotently seeds a placeholder `starter` plan (`config('subscriptions.default_plan')`) with **development placeholder limits** — they are not a pricing decision. **Added 2026-11-15:** `plan_prices` (monthly/yearly in kuruş), `plans.is_featured`, `subscriptions.plan_price_id` (nullable, unused until checkout exists), and the `/admin` Plan resource (super admins) saving through `PlanService` (code immutable, every `PlanFeature` gets a limit row, cleared prices are deactivated, one featured plan). **Phase 3 (2026-11-15):** every lifecycle transition in `SubscriptionService` (`processDue`, `cancel`, `revertCancel`, `activate`, `renew`, `markPastDue`, `resubscribe`), the hourly `subscriptions:process-lifecycle` command with the tenant-scoped `ProcessTenantSubscriptionLifecycle` job, the `PaymentGateway` contract (interface only), plan selection at registration (`/register?plan={code}`), and the `/app` dashboard widget and subscription page showing grace and expired subscriptions.

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
| `grace` | Trial or period ended without payment; online until `grace_ends_at` | Online, plus a warning on `/account` and the `/app` dashboard (owners) |
| `expired` | Grace passed; `ended_at` set | **Offline: unavailable page** |

Rules:

- **All transitions go through `SubscriptionService`.** Each one runs in a transaction and writes a `subscription_events` row.
- **Cancellation** sets `cancel_at_period_end = true` and `canceled_at`. The subscription stays `active` until the period ends, then moves to `grace`. A cancellation can be reverted before the period ends (`cancel_reverted`).
- Entering `grace` sets `grace_ends_at = boundary + grace_days`, where the boundary is the missed `trial_ends_at` or `current_period_ends_at` (decision 2026-11-15). When the job runs on time this equals `now + grace_days`; a late run (scheduler outage, a suspended tenant reactivated later) never extends access, and `access_until` doesn't change when the status moves to `grace`.
- Expiry sets `ended_at` to the time the transition is applied; access already ended at `grace_ends_at` (`access_until`).
- Re-subscribing after `expired` creates a **new** subscription row and keeps the history.
- Every transition clears the tenant's resolution cache entries, because they hold `access_until` (see [caching.md](caching.md)): saving the `Subscription` model triggers `MenuCacheObserver`, which clears them after commit. `FeatureGate::flush()` also runs after commit, so the same request or job sees the new limits.

### Transitions (`SubscriptionService`)

Each method runs in a transaction on the subscription row re-read `FOR UPDATE` through the tenant scope (so only the tenant in `TenantContext` is reachable) and checks its precondition on the locked row. **When the transition is already applied or not due, nothing is written and no event is logged** (idempotent). A transition that isn't allowed from the current state throws `InvalidSubscriptionTransitionException`.

| Method | From → to | Condition | Event (`meta`) |
|---|---|---|---|
| `processDue()` | `trialing` → `grace` | `now ≥ trial_ends_at` | `grace_started` (`reason: trial_ended`, `boundary`, `grace_ends_at`) |
| | `active` / `past_due` → `grace` | `now ≥ current_period_ends_at` | `grace_started` (`reason: period_ended`, `canceled` or `payment_failed`) |
| | `grace` → `expired` | `now ≥ grace_ends_at`; sets `ended_at` | `expired` |
| `cancel()` | `active` / `past_due` (unchanged) | period running; sets `cancel_at_period_end`, `canceled_at` | `cancel_requested` |
| `revertCancel()` | `active` / `past_due` (unchanged) | cancelled, period running; clears both | `cancel_reverted` |
| `activate()` | `trialing` / `grace` / `past_due` → `active` | first payment; sets the period, clears grace and cancellation; same period again = no-op | `activated` |
| `renew()` | `active` / `past_due` / `grace` (after a paid period) → `active` | new period end later than the current one; otherwise no-op | `renewed` |
| `markPastDue()` | `active` → `past_due` | renewal failed | `payment_failed` |
| `resubscribe()` | no live subscription → **new row** `active` | the same call again returns the existing row; any other live subscription is refused (and the partial unique index backs it up) | `reactivated` (`previous_subscription_id`) |
| `startComplimentary()` | no live subscription → **new row** `active` | free, platform-granted period (no price, no provider) ending at the given date; same call again returns the existing row, any other live subscription is refused. Used only by `ShowcaseSeeder` (period until 2099-12-31), not a payment flow | `complimentary_started` (`plan`, `reason`, `period_ends_at`) |

`processDue()` applies transitions in a loop, so a subscription that missed several (e.g. trial and grace both over) gets `grace_started` and `expired` in one run. `activate`, `renew`, `markPastDue` and `resubscribe` are the entry points for the future gateway adapter; nothing calls them yet.

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
- The tenant can still log in and see `/account` with the reactivation path. *Implemented 2026-11-15 (see [overview.md § Authentication](overview.md#authentication)):* owners see plan, status, the next key date, `access_until` and whether the menu is online; a prominent warning during `grace`; and whenever the menu is offline (expired, no subscription, or `access_until` already passed before the lifecycle job ran) "menu closed, your data is kept" with the reactivation path — the contact page and the site settings' support details, no payment button until the payment phase (`resubscribe()` is ready for it). Managers and branch staff see only whether the menu is online. The `/app` dashboard (`SubscriptionStatusWidget`) and the subscription page show the same information to owners. Subscription management in `/admin` remains out of scope.
- The online rule exists once, in `App\Support\Menu\MenuAvailability::isOnline()`; `ResolvedTenant::isOnline()` (public menu, QR) and `BusinessDashboard::isMenuOnline()` (dashboard, `/account`) both delegate to it.

## Scheduled processing

`subscriptions:process-lifecycle` (`ProcessSubscriptionLifecycle`) runs **hourly** from `routes/console.php`:

1. It is `TenantAgnostic` (`App\Tenancy\TenantAgnostic`). Its only scope bypass finds the tenants of live subscriptions with pending transitions:
   - trial ended
   - period ended
   - grace ended
   Deleted and suspended tenants are skipped: their menu is offline anyway, and because grace is anchored to the missed boundary they catch up correctly once reactivated.
2. It dispatches one `ProcessTenantSubscriptionLifecycle` job per tenant with that tenant set in `TenantContext`. The job has **no tenant id parameter**: `TenantQueueListener` carries the context in the payload, so a tenant deleted or suspended after dispatch fails the job before `handle()` runs, and a job without a context fails closed. Every dispatch is wrapped individually: with the `sync` driver a failing tenant is reported and the others continue (the command then exits non-zero); with a worker, failures land in `failed_jobs` per tenant.
3. Each job calls `SubscriptionService::processDue()` for the tenant's live subscription. Transitions are **idempotent**: re-running is safe and never double-logs.
4. Scheduled `->hourly()->withoutOverlapping()->onOneServer()`. Expiry doesn't depend on the job: requests compare `access_until` with the clock.

## Plan features and limits

`App\Enums\PlanFeature`:

| Feature | Type | Meaning |
|---|---|---|
| `max_locations` | count | Number of branches |
| `max_menus` | count | Number of menus |
| `max_items` | count | Number of items across all menus |
| `max_languages` | count | Enabled tenant locales |
| `max_qr_codes` | count | Number of QR codes |
| `max_members` | count | Active team members (owner included) plus pending invitations; checked when inviting and when reactivating a member (launch plan Faz 7) |
| `premium_themes` | boolean | Access to themes that require it |
| `custom_domain` | boolean | Custom domains (later phase; `PlanFeature::isAvailable()` false: always stored as 0, never advertised) |
| `remove_branding` | boolean | Hide the "Powered by Restano" footer |
| `qr_analytics` | boolean | Scan analytics (later phase; `PlanFeature::isAvailable()` false: always stored as 0, never advertised) |

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
  - creating new resources beyond limits is blocked by the services; the `/app` dashboard shows usage against limits (`PlanUsageWidget`, information only)

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
- Plan card bullet points (`PricingPlan::highlights()`) and the comparison table (`MarketingContent::comparison()`) are built only from `plan_limits`, in `PlanFeature` order: count features show the number or "unlimited" (`null`), boolean features a check or a dash; a missing row or `0` is "not included". A feature appears in the table only when at least one listed plan includes it, so features nobody offers are never advertised. Features that aren't built yet (`PlanFeature::isAvailable()` false: `custom_domain`, `qr_analytics`) are never "included" on the page whatever their row says; `PlanService` stores them as 0 and the admin plan form shows them disabled. Count features and boolean features are two row groups. These are display values; enforcement stays in `FeatureGate`.
- Billing FAQ: published `Faq` entries with placement `pricing` (`MarketingContent::faqs(FaqPlacement::Pricing)`); the section is hidden when there are none. Home page FAQs (placement `home`) are not shown here.
- Amounts are formatted with `NumberFormatter` (`Money::format()`, `tr_TR`), e.g. `₺1.299` (whole amounts without fraction). The English page uses the `en` locale with TRY. Limit numbers use the page locale (`1.000` / `1,000`).
- **Decided 2026-11-16:** prices are entered and shown **KDV-inclusive**; the pricing page and the home pricing preview say so under the plan cards (`marketing.pricing.vat_note`). Display only; storage unaffected.
- CTA: each plan card links to `/register?plan={code}` and shows the plan's own trial length (`Plan::trialDays()`: `plans.trial_days`, else `subscriptions.trial_days`). The page lead and the empty state link to `/register` with `subscriptions.trial_days`.

## Plan selection at registration

- `GET /register?plan={code}` preselects a plan only when it is **active and public** (`Plan::selectable()`); the form then carries it as a hidden `plan` field and names it with its trial length. Any other code is ignored.
- `CreateNewUser` accepts `plan` only as a nullable string; `TenantRegistrationService::planFor()` resolves it again on the server and falls back to the default plan (`subscriptions.default_plan`) for an inactive, hidden or unknown code. The trial then lasts the chosen plan's `trialDays()`.
- The plan code is a public identifier, never authorization; registration still grants no platform role and accepts no tenant id or role.

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
- Re-subscribing after expiry creates a new row; the partial unique index prevents two live subscriptions. *(SubscriptionLifecycleTest)*
- `access_until` is correct for each status; expiry takes effect while the cache is warm.
- The lifecycle command is idempotent and isolates per-tenant failures (worker and `sync`); a tenant's job never touches another tenant; deleted/suspended tenants are skipped, and a job whose tenant was suspended after dispatch fails without running. *(ProcessSubscriptionLifecycleTest)*
- Grace is anchored to the missed boundary; a late run never extends access; transitions clear the resolution cache after commit (not on rollback); `FeatureGate` follows transitions within the same request; expiry deletes no data.
- `/register?plan=`: an active public plan is used with its own trial days; inactive, hidden or unknown codes fall back to the default plan; pricing CTAs carry the plan code. *(RegistrationTest, PricingPageTest)*
- The dashboard widget and subscription page show grace (with its end date), cancellation and expired subscriptions (ended date, menu offline, data kept). *(DashboardTest, SubscriptionPageTest)*
- `FeatureGate` handles unlimited, count limits, boolean features and missing rows; trialing / active / past_due / grace apply the plan, expired or no subscription allows no creation; a downgrade falls back to `classic` without deleting settings.
