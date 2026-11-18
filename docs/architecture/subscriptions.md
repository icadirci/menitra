# Subscriptions and Pricing

> Status: **Approved — v0.5** (2026-11-18: **payments** — manual period payments through iyzico Checkout Form, see [Payments](#payments)). v0.4 (2026-11-15): Phase 3 lifecycle — transitions, hourly command, grace anchored to the missed boundary, `/register?plan=`. v0.3: `max_qr_codes`, `FeatureGate` semantics for missing subscriptions, placeholder limits. v0.2: Provider-agnostic foundation.

## Contents

- [Scope](#scope)
- [Data model](#data-model)
- [Lifecycle](#lifecycle)
- [Public menu access](#public-menu-access)
- [Scheduled processing](#scheduled-processing)
- [Plan features and limits](#plan-features-and-limits)
- [Payments](#payments)
- [Pricing page](#pricing-page)
- [Data retention](#data-retention)
- [Configuration](#configuration)
- [Tests](#tests)

## Scope

| Supported now | Later |
|---|---|
| Monthly and annual prices (TRY) | Automatic recurring charges (iyzico Subscription API or stored cards) |
| Free trials, optional "pay now" at registration | e-Arşiv invoice integration (receipts only now) |
| Manual period payments through iyzico Checkout Form (3-D Secure) | Downgrades during a paid period (only at renewal, after the period) |
| Payment records, receipts, reminder mails; immediate prorated upgrades | Refunds from the app (iyzico merchant panel now) |
| Cancellation at period end | Coupons |
| Expiration and grace periods | Per-plan grace overrides |
| Plan-based feature restrictions | |

## Data model

Tables are defined in [database.md](database.md#subscriptions).

- `plans` + `plan_translations`: a plan's identity and its tr/en name and description.
- `plan_prices`: one row per plan, interval and currency. Amounts are in kuruş; the default currency is `TRY`.
- Per-branch pricing (optional, 2026-11-16): `plans.included_locations` (branches covered by the base price) and `plan_prices.extra_location_amount` (kuruş per further branch, per interval). Both null = no per-branch charge. Display only for now: the charge for a tenant's extra branches is computed by the payment work (iyzico), from the tenant's active locations.
- `plan_limits`: one row per plan and `PlanFeature`.
- `subscriptions`: a tenant's subscription history. **At most one live subscription per tenant**, enforced by a partial unique index on `tenant_id` where `ended_at IS NULL`.
- `subscription_events`: an append-only audit trail of every transition.

**Implemented so far (2026-11-15):** `plans`, `plan_translations`, `plan_limits`, `subscriptions` (without `plan_price_id`), `subscription_events`, `SubscriptionService::startTrial()` used by registration, and `FeatureGate` (Phase B1). `PlanSeeder` idempotently seeds a placeholder `starter` plan (`config('subscriptions.default_plan')`) with **development placeholder limits** — they are not a pricing decision. **Added 2026-11-15:** `plan_prices` (monthly/yearly in kuruş), `plans.is_featured`, `subscriptions.plan_price_id` (nullable, unused until checkout exists), and the `/admin` Plan resource (super admins) saving through `PlanService` (code immutable, every `PlanFeature` gets a limit row, cleared prices are deactivated, one featured plan). **Phase 3 (2026-11-15):** every lifecycle transition in `SubscriptionService` (`processDue`, `cancel`, `revertCancel`, `activate`, `renew`, `markPastDue`, `resubscribe`), the hourly `subscriptions:process-lifecycle` command with the tenant-scoped `ProcessTenantSubscriptionLifecycle` job, the `PaymentGateway` contract (interface only), plan selection at registration (`/register?plan={code}`), and the `/app` dashboard widget and subscription page showing grace and expired subscriptions.

**Plans (decided 2026-11-16, seeded by `PlanSeeder`, replacing the placeholder above):** every plan has the same limits (1 branch, 5 menus, 150 items, 2 languages, 5 QR codes, 3 members) except where noted; yearly = 8 × monthly.

| Code | Name | Monthly | Yearly | Differences |
|---|---|---|---|---|
| `starter` (registration default) | Başlangıç / Starter | ₺300 | ₺2.400 | No `premium_themes`: only the basic themes `classic` and `minimal` |
| `pro` (featured) | Profesyonel / Professional | ₺500 | ₺4.000 | All themes |
| `multi-branch` | Çoklu Şube / Multi-Branch | ₺700 | ₺5.600 | All themes; unlimited branches, 2 included, each further branch +₺200/month (+₺1.600/year); 10 menus, 500 items, 3 languages, 50 QR codes, 15 members |

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

`processDue()` applies transitions in a loop, so a subscription that missed several (e.g. trial and grace both over) gets `grace_started` and `expired` in one run. `activate`, `renew` (optionally with the paid `PlanPrice`), `changePlan` and `resubscribe` are called by `PaymentProcessor` (see [Payments](#payments)). `changePlan()` switches the plan only while no paid period runs (`trialing`, `grace`, `past_due`; event `plan_changed`); `markPastDue` stays unused because nothing is charged automatically.

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
- The tenant can still log in and see `/account` with the reactivation path. *Implemented 2026-11-15 (see [overview.md § Authentication](overview.md#authentication)):* owners see plan, status, the next key date, `access_until` and whether the menu is online; a prominent warning during `grace`; and whenever the menu is offline (expired, no subscription, or `access_until` already passed before the lifecycle job ran) "menu closed, your data is kept" with the reactivation path — the contact page and the site settings' support details, and (since 2026-11-18) a "pay now" link to the checkout page in `/app`. Managers and branch staff see only whether the menu is online. The `/app` dashboard (`SubscriptionStatusWidget`) and the subscription page show the same information to owners. Subscription management in `/admin` remains out of scope.
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

## Payments

> Decided 2026-11-18: iyzico, **manual period payments** (the owner pays each period; nothing is charged automatically), official SDK `iyzico/iyzipay-php`, trial kept with an optional "pay now" at registration, receipts instead of invoices.

### Flow

```
/app/{slug}/subscription ──"Pay / renew"──▶ Checkout page (plan, period, amount, billing details, terms)
        │ CheckoutService::start()
        │   eligibility + plan check, billing profile required, ≤ 10 checkouts/hour
        │   BillingCalculator: plan price + extra branches × extra_location_amount
        │   payments row (pending, encrypted billing snapshot) → PaymentGateway::initializeCheckout()
        ▼
iyzico hosted payment page (3-D Secure, single payment, no installments)
        │ browser POST token ──▶ /billing/iyzico/callback ──┐
        │ webhook (X-IYZ-SIGNATURE-V3) ──▶ ProcessPayment job ─┤──▶ PaymentProcessor::complete()
        │ every 10 min: billing:reconcile-payments ───────────┘     retrieveCheckout(token) from the API
        ▼                                                           verify reference, basket, amount, signature
303 ▶ /app/{slug}/subscription?payment={public_id}                 → SubscriptionService transition (locked, once)
```

- **Nothing the browser or webhook sends is trusted.** The token only finds the payment (`PaymentTokenResolver`, documented scope bypass); the result always comes from iyzico's retrieve API, whose HMAC response signature is verified (`paymentStatus:paymentId:currency:basketId:conversationId:paidPrice:price:token`). `conversationId` and `basketId` must equal the payment's `public_id` and `paidPrice` must equal the stored amount, otherwise the payment is marked `failed` (`verification_failed`), nothing is applied and a critical log line asks for a manual review/refund.
- `fraudStatus` 1 = success; 0 (review) stays pending until the webhook / reconciliation; -1 = failed.
- **Exactly once:** `PaymentProcessor` locks the payment row; a `succeeded` payment is never applied again, so the callback, the webhook job and the reconciliation job may race safely. A provider error leaves the payment `pending`.
- **Which transition** is decided from the live subscription when the money arrives:

| Live subscription | Transition | Period |
|---|---|---|
| none (expired) | `resubscribe()` (new row) | now → + interval |
| `trialing` | [`changePlan()`] + `activate()` | trial end (or now) → + interval: trial days are kept |
| `grace` / `past_due` | [`changePlan()`] + `activate()` | now → + interval |
| `active` | `renew()` (same plan; a different plan is logged and extends the active plan) | current period end → + interval |
| `active`, upgrade payment | `upgrade()`: new plan and price at once (event `plan_changed`, `mode: upgrade`) | unchanged; if the period ended meanwhile the plan still changes (`changePlan()`) and the next period is due |

- **Who and when** (`CheckoutService::eligibility()`): owners only (`TenantPolicy::manageSettings`, also in the service). Without a running paid period (none, trialing, grace, past due) any selectable plan, monthly or yearly. While `active`: the current plan within `billing.renewal_window_days` (30) before the period ends (the renewal may switch monthly ↔ yearly), and **at any time an upgrade** to a selectable plan priced higher in the period's interval. Downgrades during a paid period are not offered.
- **Upgrades** (decided 2026-11-18: the higher plan is active at once, the owner never waits). `BillingCalculator::upgradeQuote()`: with `remaining / period` of the running period left, `charge = new plan's full quote (branches included) × remaining / period`, `credit = value of the period on the current plan × remaining / period`, `total = max(charge − credit, ₺1)`. The value is what was actually paid for this period on this plan (the period payment, branches included), else the plan's list price (after an earlier upgrade or for a period not paid in the app). The payment is created with `kind = upgrade`, `base_amount = charge`, `credit_amount`, `prorated_days` and the period's own dates and interval; one basket item. On success `SubscriptionService::upgrade()` switches plan and price, keeps the period, and `FeatureGate` / the menu caches follow at once.
- **Amount:** `BillingCalculator` is the only place it is computed: the plan price for the interval plus `max(0, branches − included_locations) × extra_location_amount` (branches counted like `max_locations`). Sent to iyzico as basket items (plan + extra branches), KDV-inclusive.
- **Callback route** (`routes/billing.php`) runs **without the `web` group**: iyzico's return is a cross-site POST without the session cookie, and a new session would overwrite the customer's cookie. No CSRF, `throttle:billing` (60/min/IP), central domain only. It redirects with 303 to the subscription page, which reports the payment's outcome through the tenant scope.
- **Webhook:** `POST /billing/iyzico/webhook`, signature `HMAC-SHA256(secretKey, secretKey + iyziEventType + iyziPaymentId + token + paymentConversationId + status)` (hex), only `CHECKOUT_FORM_AUTH`. Invalid → 403; unknown or mismatched → 200 and ignored; valid → `ProcessPayment` job (tenant from the context, retried with backoff).
- **Reconciliation:** `billing:reconcile-payments` (every 10 minutes, `TenantAgnostic`, active tenants only) settles pending checkouts older than 5 minutes; pending ones older than `billing.pending_expires_minutes` (120) become `expired`.
- **Reminders:** `billing:send-reminders` (daily 09:00 Europe/Istanbul) mails the owner 7 and 1 days (`billing.reminder_days`) before the trial or period ends (only the closest threshold) and once when grace starts; at most once per subscription, type, boundary and threshold (`billing_reminders`). Cancelled subscriptions get no reminder.
- **Checkout page** (`/app/{slug}/subscription/checkout`, Filament native components): billing period toggle (yearly preselected, "yearly · %X off", "pay 8 months instead of 12"), plan cards (price per period, per-month equivalent, saving amount and %, current / most popular / upgrade badges, highlights from `plan_limits` via `MarketingContent::pricingPlan()`, branch pricing, the prorated amount of an upgrade), billing details (tax id masked) and an order summary (base, extra branches or upgrade charge and credit, total, yearly saving, "switch to yearly" hint, period covered, terms, pay button with the amount, trust notes). Everything shown is recomputed by the services when the checkout starts.
- **Receipts:** on success `receipt_number` `RST-{year}-{id:06}`, a queued `PaymentReceiptMail` to the billing email (business default locale) and a printable receipt page in `/app` (`subscription/receipt?payment=`). A receipt is not an invoice: the e-Arşiv invoice is issued outside the app from the billing details.
- **Billing details** (`tenant_billing_profiles`, `BillingProfileService`, owner-only): individual (TCKN, checksum) or corporate (VKN, checksum, tax office), billing email, optional mobile (E.164), address. `tax_id` is encrypted; each payment stores an encrypted snapshot. They are sent to iyzico as buyer and billing address.
- **Registration:** the form offers "try free for N days" (default) or "pay now" (`start=pay`). Both start the trial; "pay now" redirects (`RegisteredResponse`) to the checkout page, which first passes the email verification (intended URL).
- **`/admin`:** a tenant's payments are listed read-only (`PaymentsRelationManager`, `TenantInspector`; no billing details). Refunds are made in the iyzico merchant panel.
- **Adapter:** `App\Contracts\PaymentGateway` (`initializeCheckout`, `retrieveCheckout`, `parseWebhook`), implemented by `IyzicoGateway`, the only class that uses the SDK. The SDK's curl wrapper has no timeouts; `IyzicoCurl` adds 10 s connect / 30 s total and keeps TLS verification. Only masked card data (association, family, last four) leaves the adapter.

### Production checklist (iyzico)

1. Approved iyzico merchant account; production keys in `.env` (`IYZICO_API_KEY`, `IYZICO_SECRET_KEY`, `IYZICO_BASE_URL=https://api.iyzipay.com`); sandbox keys from sandbox-merchant.iyzipay.com for staging.
2. HTTPS central domain; in the merchant panel (Settings → Merchant Notifications) the webhook URL `https://restano.app/billing/iyzico/webhook`, and ask iyzico (entegrasyon@iyzico.com) to enable **X-IYZ-SIGNATURE-V3**, otherwise every webhook is refused and payments are settled by the callback and the reconciliation only.
3. The queue worker and scheduler must run (`ProcessPayment`, reconciliation, reminders, mails).
4. Behind a proxy/load balancer configure `trustProxies`, so the buyer IP sent to iyzico is the customer's.
5. Legal: distance-sales pre-information and contract texts reviewed (the checkout links the terms page); KVKK: add iyzico to the processor list (Faz 10).
6. Test in sandbox with iyzico's test cards (success, decline, 3-D Secure) before switching keys.

### Configuration

`config/billing.php`: `gateway` (`iyzico`), `renewal_window_days` (30), `pending_expires_minutes` (120), `reconcile_after_minutes` (5), `reminder_days` ([7, 1]). Credentials: `config/services.php` → `iyzico`.

## Pricing page

- Routes: `/fiyatlar` (tr) and `/en/pricing` (en), route `marketing.{locale}.pricing`, `PricingController` → `MarketingContent` → `marketing/pricing.blade.php`. Central domain only; both paths are reserved tenant slugs.
- Active, public plans with an active monthly price are read through `MarketingContent::plans()` (DTO `PricingPlan`: localised name/description, monthly and yearly `Money`, yearly-per-month and saving % computed from stored prices, and the plan's `plan_limits` as `feature => limit_value`). Only these allow-listed fields reach the views. Caching as `qrmenu:plans:{locale}` is deferred until `MenuCacheKeys` exists (Phase 6); the queries are eager-loaded (`translations`, `prices`, `limits`) and small.
- Monthly/annual toggle (Alpine, `x-data="{ yearly: false }"`, an ARIA radiogroup). Without JS the monthly prices are shown. The toggle appears only when at least one plan has a yearly price; a plan without one keeps its monthly price with a "monthly only" note. The yearly view shows the yearly total, the per-month equivalent (`intdiv(yearly, 12)`) and the plan's saving.
- **The annual saving is computed from stored prices, never hard-coded:** `floor((12 × monthly − yearly) × 100 / (12 × monthly))`, shown only when positive. The toggle badge (`MarketingContent::yearlySaving()`) shows the largest saving — "save X%" when every yearly-priced plan saves the same, otherwise "save up to X%".
- Per-branch pricing: a plan with `extra_location_amount` shows "N branches included · each additional branch +₺X" under the price of each billing period.
- Plan card bullet points (`PricingPlan::highlights()`) also state the number of menu themes ("2 basic menu themes" without `premium_themes`, "All menu themes (5)" with it). Bullet points and the comparison table (`MarketingContent::comparison()`) are built only from `plan_limits`, in `PlanFeature` order: count features show the number or "unlimited" (`null`), boolean features a check or a dash; a missing row or `0` is "not included". A feature appears in the table only when at least one listed plan includes it, so features nobody offers are never advertised. Features that aren't built yet (`PlanFeature::isAvailable()` false: `custom_domain`, `qr_analytics`) are never "included" on the page whatever their row says; `PlanService` stores them as 0 and the admin plan form shows them disabled. Count features and boolean features are two row groups. These are display values; enforcement stays in `FeatureGate`.
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
- Payments *(tests/Feature/Billing)*: server-computed amounts incl. extra branches; eligibility (renewal window, plan change only without a paid period, hidden plans, owners only, tenant in context, attempt limit); provider error keeps a failed payment; callback applies trial → active from the trial end, active → renewed from the period end, grace + plan change, expired → new row; never twice; declined, wrong amount, wrong reference and pending/stale results change nothing; unknown tokens reveal nothing; no session cookie on the callback; webhook signature (valid, invalid, missing, mismatched) and its status never trusted; reconciliation and suspended tenants; reminders once per threshold, closest only, grace once, escaped names; billing profile validation (TCKN/VKN checksums), encryption and owner-only; receipt page and other tenants' payments (404); "pay now" at registration.
- `FeatureGate` handles unlimited, count limits, boolean features and missing rows; trialing / active / past_due / grace apply the plan, expired or no subscription allows no creation; a downgrade falls back to `classic` without deleting settings.
