<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BillingReminderType;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantRole;
use App\Filament\App\Pages\Checkout;
use App\Mail\BillingReminderMail;
use App\Models\BillingReminder;
use App\Models\Subscription;
use App\Models\TenantMembership;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Payment reminder for one tenant's owner (billing:send-reminders, daily):
 * before the trial or the paid period ends (billing.reminder_days, only the
 * closest threshold, so a late start never sends two mails at once) and once
 * when grace starts. At most once per subscription, type, boundary and
 * threshold (billing_reminders unique key). Tenant from the dispatching context.
 */
class SendTenantBillingReminders implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function handle(TenantContext $context): void
    {
        $subscription = Subscription::query()->with('plan.translations')->whereNull('ended_at')->latest('id')->first();

        if ($subscription === null || $subscription->cancel_at_period_end) {
            return;
        }

        $due = $this->due($subscription, CarbonImmutable::now());

        if ($due === null) {
            return;
        }

        [$type, $boundary, $days] = $due;
        $tenant = $context->tenant();
        $owner = TenantMembership::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('role', TenantRole::Owner)
            ->where('is_active', true)
            ->with('user')
            ->first()?->user;

        if ($owner === null || $owner->isDeactivated()) {
            return;
        }

        try {
            DB::transaction(function () use ($subscription, $type, $boundary, $days, $tenant, $owner): void {
                $reminder = new BillingReminder;
                $reminder->forceFill([
                    'subscription_id' => $subscription->getKey(),
                    'type' => $type,
                    'boundary_at' => $boundary,
                    'days_before' => $days,
                    'sent_at' => CarbonImmutable::now(),
                ])->save();

                $locale = $tenant->default_locale;
                $mail = new BillingReminderMail(
                    type: $type,
                    businessName: $tenant->translate($locale)?->name ?? $tenant->slug,
                    planName: $subscription->plan?->translate($locale)?->name ?? (string) $subscription->plan?->code,
                    boundaryAt: $boundary,
                    days: $days,
                    url: Checkout::getUrl(panel: 'app', tenant: $tenant),
                );

                DB::afterCommit(fn () => Mail::to($owner->email)->locale($locale)->queue($mail));
            });
        } catch (UniqueConstraintViolationException) {
            // Already sent (concurrent or repeated run).
        }
    }

    /**
     * @return array{BillingReminderType, CarbonImmutable, int}|null
     */
    private function due(Subscription $subscription, CarbonImmutable $now): ?array
    {
        if ($subscription->status === SubscriptionStatus::Grace) {
            $boundary = $subscription->grace_ends_at;

            if ($boundary === null || ! $boundary->isAfter($now) || $this->sent($subscription, BillingReminderType::GraceStarted, $boundary, 0)) {
                return null;
            }

            return [BillingReminderType::GraceStarted, $boundary, 0];
        }

        [$type, $boundary] = match ($subscription->status) {
            SubscriptionStatus::Trialing => [BillingReminderType::TrialEnding, $subscription->trial_ends_at],
            SubscriptionStatus::Active, SubscriptionStatus::PastDue => [BillingReminderType::PeriodEnding, $subscription->current_period_ends_at],
            default => [null, null],
        };

        if ($type === null || $boundary === null || ! $boundary->isAfter($now)) {
            return null;
        }

        $thresholds = collect(config('billing.reminder_days'))->map(fn ($days): int => (int) $days)->sort()->values();
        $threshold = $thresholds->first(fn (int $days): bool => $now->greaterThanOrEqualTo($boundary->subDays($days)));

        if ($threshold === null) {
            return null;
        }

        return $this->sent($subscription, $type, $boundary, $threshold) ? null : [$type, $boundary, $threshold];
    }

    /**
     * A reminder for this boundary at this or a closer threshold was sent already.
     */
    private function sent(Subscription $subscription, BillingReminderType $type, CarbonImmutable $boundary, int $threshold): bool
    {
        return BillingReminder::query()
            ->where('subscription_id', $subscription->getKey())
            ->where('type', $type)
            ->where('boundary_at', $boundary)
            ->where('days_before', '<=', $threshold)
            ->exists();
    }
}
