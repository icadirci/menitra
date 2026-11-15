<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionStatus;
use App\Exceptions\CrossTenantWriteException;
use App\Exceptions\InvalidSubscriptionTransitionException;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only place subscription state changes (docs/architecture/subscriptions.md).
 *
 * - Every transition runs in a transaction on the row locked FOR UPDATE and
 *   writes one subscription_events row.
 * - Transitions are idempotent: when the locked row shows the transition is
 *   already applied (or not due), nothing is written and no event is logged.
 * - Grace is anchored to the boundary that was missed (trial or period end),
 *   so grace_ends_at equals the access_until shown before the transition and a
 *   late run (scheduler outage, reactivated tenant) never extends access.
 * - Saving the row clears the tenant's resolution cache after commit
 *   (MenuCacheObserver: the entries hold access_until); FeatureGate is
 *   flushed after commit so the same request/job sees the new state.
 * - Rows are read through the tenant scope, so a service call only ever
 *   touches the tenant in TenantContext.
 *
 * activate / renew / markPastDue / resubscribe are the provider-agnostic
 * entry points for the future PaymentGateway adapter.
 */
class SubscriptionService
{
    private const array PAID = [SubscriptionStatus::Active, SubscriptionStatus::PastDue];

    public function __construct(private readonly TenantContext $context) {}

    public function startTrial(Tenant $tenant, Plan $plan): Subscription
    {
        return DB::transaction(function () use ($tenant, $plan): Subscription {
            $now = CarbonImmutable::now();

            $subscription = new Subscription;
            $subscription->forceFill([
                'tenant_id' => $tenant->getKey(),
                'plan_id' => $plan->getKey(),
                'status' => SubscriptionStatus::Trialing,
                'trial_ends_at' => $now->addDays($plan->trialDays()),
            ])->save();

            $this->record($subscription, SubscriptionEventType::TrialStarted, null, SubscriptionStatus::Trialing, $now, [
                'plan' => $plan->code,
                'trial_days' => $plan->trialDays(),
            ]);

            return $subscription;
        });
    }

    /**
     * Applies every transition that is due by now: trial or period end →
     * grace, grace end → expired. Several in one call when runs were missed.
     * Returns the number of transitions applied (0 = nothing due).
     */
    public function processDue(Subscription $subscription): int
    {
        $applied = 0;

        $this->transition($subscription, function (Subscription $locked, CarbonImmutable $now) use (&$applied): void {
            while ($this->applyDue($locked, $now)) {
                $applied++;
            }
        });

        return $applied;
    }

    /**
     * Cancels at period end: the subscription stays active (or past due)
     * until current_period_ends_at, then moves to grace.
     */
    public function cancel(Subscription $subscription): Subscription
    {
        return $this->transition($subscription, function (Subscription $locked, CarbonImmutable $now): void {
            $this->ensureOpenPeriod($locked, $now);

            if ($locked->cancel_at_period_end) {
                return;
            }

            $locked->forceFill(['cancel_at_period_end' => true, 'canceled_at' => $now])->save();
            $this->record($locked, SubscriptionEventType::CancelRequested, $locked->status, $locked->status, $now, [
                'period_ends_at' => $locked->current_period_ends_at?->toIso8601String(),
            ]);
        });
    }

    /**
     * Withdraws a cancellation before the period ends.
     */
    public function revertCancel(Subscription $subscription): Subscription
    {
        return $this->transition($subscription, function (Subscription $locked, CarbonImmutable $now): void {
            if (! $locked->cancel_at_period_end) {
                return;
            }

            $this->ensureOpenPeriod($locked, $now);

            $locked->forceFill(['cancel_at_period_end' => false, 'canceled_at' => null])->save();
            $this->record($locked, SubscriptionEventType::CancelReverted, $locked->status, $locked->status, $now);
        });
    }

    /**
     * First successful payment: trialing, grace or past due → active with the
     * paid period. Repeating it with the same period end is a no-op.
     */
    public function activate(
        Subscription $subscription,
        CarbonImmutable $periodStartsAt,
        CarbonImmutable $periodEndsAt,
        ?PlanPrice $price = null,
        ?string $provider = null,
        ?string $providerReference = null,
    ): Subscription {
        $this->ensurePeriod($periodStartsAt, $periodEndsAt);

        return $this->transition($subscription, function (Subscription $locked, CarbonImmutable $now) use ($periodStartsAt, $periodEndsAt, $price, $provider, $providerReference): void {
            if ($locked->status === SubscriptionStatus::Active && $locked->current_period_ends_at?->equalTo($periodEndsAt)) {
                return;
            }

            if (! in_array($locked->status, [SubscriptionStatus::Trialing, SubscriptionStatus::Grace, SubscriptionStatus::PastDue], true)) {
                throw InvalidSubscriptionTransitionException::from($locked->status);
            }

            $this->ensurePriceOfPlan($price, (int) $locked->plan_id);
            $from = $locked->status;

            $locked->forceFill([
                'status' => SubscriptionStatus::Active,
                'current_period_starts_at' => $periodStartsAt,
                'current_period_ends_at' => $periodEndsAt,
                'grace_ends_at' => null,
                'cancel_at_period_end' => false,
                'canceled_at' => null,
                'plan_price_id' => $price?->getKey() ?? $locked->plan_price_id,
                'provider' => $provider ?? $locked->provider,
                'provider_reference' => $providerReference ?? $locked->provider_reference,
            ])->save();

            $this->record($locked, SubscriptionEventType::Activated, $from, SubscriptionStatus::Active, $now, [
                'period_ends_at' => $periodEndsAt->toIso8601String(),
            ]);
        });
    }

    /**
     * A renewal payment: active, past due or grace (after a paid period) →
     * active with the new period. A period end at or before the current one
     * is a repeat and changes nothing.
     */
    public function renew(Subscription $subscription, CarbonImmutable $periodEndsAt, ?CarbonImmutable $periodStartsAt = null): Subscription
    {
        return $this->transition($subscription, function (Subscription $locked, CarbonImmutable $now) use ($periodEndsAt, $periodStartsAt): void {
            $currentEnd = $locked->current_period_ends_at;

            if (! in_array($locked->status, [...self::PAID, SubscriptionStatus::Grace], true) || $currentEnd === null) {
                throw InvalidSubscriptionTransitionException::from($locked->status);
            }

            if ($periodEndsAt->lessThanOrEqualTo($currentEnd)) {
                return;
            }

            $startsAt = $periodStartsAt ?? $currentEnd;
            $this->ensurePeriod($startsAt, $periodEndsAt);
            $from = $locked->status;

            $locked->forceFill([
                'status' => SubscriptionStatus::Active,
                'current_period_starts_at' => $startsAt,
                'current_period_ends_at' => $periodEndsAt,
                'grace_ends_at' => null,
                'cancel_at_period_end' => false,
                'canceled_at' => null,
            ])->save();

            $this->record($locked, SubscriptionEventType::Renewed, $from, SubscriptionStatus::Active, $now, [
                'period_ends_at' => $periodEndsAt->toIso8601String(),
            ]);
        });
    }

    /**
     * A failed renewal payment while the period is still running.
     */
    public function markPastDue(Subscription $subscription): Subscription
    {
        return $this->transition($subscription, function (Subscription $locked, CarbonImmutable $now): void {
            if ($locked->status === SubscriptionStatus::PastDue) {
                return;
            }

            if ($locked->status !== SubscriptionStatus::Active) {
                throw InvalidSubscriptionTransitionException::from($locked->status);
            }

            $locked->forceFill(['status' => SubscriptionStatus::PastDue])->save();
            $this->record($locked, SubscriptionEventType::PaymentFailed, SubscriptionStatus::Active, SubscriptionStatus::PastDue, $now);
        });
    }

    /**
     * Subscribing again after expiry: a new active row; earlier rows stay as
     * history. Repeating the same call returns the existing row; any other
     * live subscription is refused (also by the partial unique index).
     */
    public function resubscribe(
        Tenant $tenant,
        Plan $plan,
        CarbonImmutable $periodStartsAt,
        CarbonImmutable $periodEndsAt,
        ?PlanPrice $price = null,
        ?string $provider = null,
        ?string $providerReference = null,
    ): Subscription {
        $this->ensureCurrentTenant($tenant);
        $this->ensurePeriod($periodStartsAt, $periodEndsAt);
        $this->ensurePriceOfPlan($price, (int) $plan->getKey());

        return DB::transaction(function () use ($tenant, $plan, $periodStartsAt, $periodEndsAt, $price, $provider, $providerReference): Subscription {
            // Serialises concurrent calls for this tenant (the tenant equals the context, checked above).
            Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->first();

            $live = Subscription::query()->whereNull('ended_at')->first();

            if ($live !== null) {
                if ($live->status === SubscriptionStatus::Active
                    && (int) $live->plan_id === (int) $plan->getKey()
                    && $live->current_period_ends_at?->equalTo($periodEndsAt)) {
                    return $live;
                }

                throw InvalidSubscriptionTransitionException::liveSubscriptionExists();
            }

            $now = CarbonImmutable::now();
            $previous = Subscription::query()->latest('id')->first();

            $subscription = new Subscription;
            $subscription->forceFill([
                'tenant_id' => $tenant->getKey(),
                'plan_id' => $plan->getKey(),
                'plan_price_id' => $price?->getKey(),
                'status' => SubscriptionStatus::Active,
                'current_period_starts_at' => $periodStartsAt,
                'current_period_ends_at' => $periodEndsAt,
                'provider' => $provider,
                'provider_reference' => $providerReference,
            ])->save();

            $this->record($subscription, SubscriptionEventType::Reactivated, $previous?->status, SubscriptionStatus::Active, $now, [
                'plan' => $plan->code,
                'previous_subscription_id' => $previous?->getKey(),
                'period_ends_at' => $periodEndsAt->toIso8601String(),
            ]);

            DB::afterCommit(fn () => app(FeatureGate::class)->flush());

            return $subscription;
        });
    }

    /**
     * One due transition on the locked row; false when nothing is due.
     */
    private function applyDue(Subscription $locked, CarbonImmutable $now): bool
    {
        $status = $locked->status;

        if ($status === SubscriptionStatus::Trialing && $locked->trial_ends_at !== null && $now->greaterThanOrEqualTo($locked->trial_ends_at)) {
            $this->enterGrace($locked, $locked->trial_ends_at, 'trial_ended', $now);

            return true;
        }

        if (in_array($status, self::PAID, true) && $locked->current_period_ends_at !== null && $now->greaterThanOrEqualTo($locked->current_period_ends_at)) {
            $reason = match (true) {
                $locked->cancel_at_period_end => 'canceled',
                $status === SubscriptionStatus::PastDue => 'payment_failed',
                default => 'period_ended',
            };
            $this->enterGrace($locked, $locked->current_period_ends_at, $reason, $now);

            return true;
        }

        if ($status === SubscriptionStatus::Grace && $locked->grace_ends_at !== null && $now->greaterThanOrEqualTo($locked->grace_ends_at)) {
            $locked->forceFill(['status' => SubscriptionStatus::Expired, 'ended_at' => $now])->save();
            $this->record($locked, SubscriptionEventType::Expired, SubscriptionStatus::Grace, SubscriptionStatus::Expired, $now, [
                'grace_ends_at' => $locked->grace_ends_at->toIso8601String(),
            ]);

            return true;
        }

        return false;
    }

    private function enterGrace(Subscription $locked, CarbonImmutable $boundary, string $reason, CarbonImmutable $now): void
    {
        $from = $locked->status;
        $graceEndsAt = $boundary->addDays((int) config('subscriptions.grace_days'));

        $locked->forceFill(['status' => SubscriptionStatus::Grace, 'grace_ends_at' => $graceEndsAt])->save();
        $this->record($locked, SubscriptionEventType::GraceStarted, $from, SubscriptionStatus::Grace, $now, [
            'reason' => $reason,
            'boundary' => $boundary->toIso8601String(),
            'grace_ends_at' => $graceEndsAt->toIso8601String(),
        ]);
    }

    /**
     * Runs $apply on the row re-read FOR UPDATE through the tenant scope and
     * refreshes the caller's instance afterwards.
     *
     * @param  Closure(Subscription, CarbonImmutable): void  $apply
     */
    private function transition(Subscription $subscription, Closure $apply): Subscription
    {
        $locked = DB::transaction(function () use ($subscription, $apply): Subscription {
            $locked = Subscription::query()->whereKey($subscription->getKey())->lockForUpdate()->firstOrFail();

            $apply($locked, CarbonImmutable::now());

            DB::afterCommit(fn () => app(FeatureGate::class)->flush());

            return $locked;
        });

        $subscription->setRawAttributes($locked->getAttributes(), true);

        return $subscription;
    }

    private function ensureOpenPeriod(Subscription $locked, CarbonImmutable $now): void
    {
        if (! in_array($locked->status, self::PAID, true)
            || $locked->current_period_ends_at === null
            || $now->greaterThanOrEqualTo($locked->current_period_ends_at)) {
            throw InvalidSubscriptionTransitionException::from($locked->status);
        }
    }

    private function ensurePeriod(CarbonImmutable $startsAt, CarbonImmutable $endsAt): void
    {
        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new InvalidArgumentException('A subscription period must end after it starts.');
        }
    }

    private function ensurePriceOfPlan(?PlanPrice $price, int $planId): void
    {
        if ($price !== null && (int) $price->plan_id !== $planId) {
            throw new InvalidArgumentException('The price does not belong to the subscription\'s plan.');
        }
    }

    private function ensureCurrentTenant(Tenant $tenant): void
    {
        if (! $this->context->check() || $this->context->id() !== (int) $tenant->getKey()) {
            throw new CrossTenantWriteException('Subscriptions can only be changed for the tenant in the current TenantContext.');
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function record(
        Subscription $subscription,
        SubscriptionEventType $type,
        ?SubscriptionStatus $from,
        ?SubscriptionStatus $to,
        CarbonImmutable $at,
        array $meta = [],
    ): void {
        (new SubscriptionEvent)->forceFill([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->getKey(),
            'type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'meta' => $meta,
            'occurred_at' => $at,
        ])->save();
    }
}
