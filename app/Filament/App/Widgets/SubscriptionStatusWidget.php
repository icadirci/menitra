<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Enums\SubscriptionStatus;
use App\Filament\App\Pages\ViewSubscription;
use App\Filament\App\Support\PanelContext;
use App\Models\Subscription;
use App\Services\Dashboard\BusinessDashboard;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Subscription status (read-only): plan and status, the next key date
 * (trial / grace / period end), until when the public menu stays online
 * (Subscription::accessUntil()) and whether it is online now. Owner-only,
 * like the subscription page (TenantPolicy::manageSettings).
 */
class SubscriptionStatusWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $context = app(TenantContext::class);

        return $context->check() && (PanelContext::user()?->can('manageSettings', $context->tenant()) ?? false);
    }

    protected function getHeading(): ?string
    {
        return __('panels.app.dashboard.subscription.heading');
    }

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $dashboard = app(BusinessDashboard::class);
        $subscription = $dashboard->liveSubscription();
        $url = ViewSubscription::getUrl();

        if ($subscription === null) {
            return [
                Stat::make(__('panels.app.dashboard.subscription.plan'), __('panels.app.dashboard.subscription.none'))
                    ->description(__('panels.app.dashboard.subscription.none_help'))
                    ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                    ->color('danger')
                    ->url($url),
                $this->onlineStat($dashboard->isMenuOnline()),
            ];
        }

        return array_values(array_filter([
            Stat::make(__('panels.app.dashboard.subscription.plan'), $subscription->plan?->translate()?->name ?? $subscription->plan?->code ?? '—')
                ->description($subscription->status->label())
                ->color($subscription->status->color())
                ->url($url),
            $this->keyDateStat($subscription),
            $this->accessStat($subscription),
            $this->onlineStat($dashboard->isMenuOnline()),
        ]));
    }

    private function keyDateStat(Subscription $subscription): ?Stat
    {
        [$label, $date] = match ($subscription->status) {
            SubscriptionStatus::Trialing => ['trial_ends_at', $subscription->trial_ends_at],
            SubscriptionStatus::Grace => ['grace_ends_at', $subscription->grace_ends_at],
            SubscriptionStatus::Active, SubscriptionStatus::PastDue => ['current_period_ends_at', $subscription->current_period_ends_at],
            SubscriptionStatus::Expired => [null, null],
        };

        if ($label === null || $date === null) {
            return null;
        }

        $stat = Stat::make(__("panels.app.subscription.fields.{$label}"), $this->date($date))
            ->description($this->remaining($date))
            ->color($subscription->status === SubscriptionStatus::Grace ? 'danger' : ($this->daysLeft($date) <= 3 ? 'warning' : 'gray'));

        if ($subscription->cancel_at_period_end) {
            $stat->description(__('panels.app.subscription.fields.cancel_at_period_end_yes').' · '.$this->remaining($date))->color('warning');
        }

        return $stat;
    }

    private function accessStat(Subscription $subscription): Stat
    {
        $accessUntil = $subscription->accessUntil();

        return Stat::make(__('panels.app.subscription.fields.access_until'), $this->date($accessUntil))
            ->description($accessUntil === null ? __('panels.app.dashboard.subscription.ended') : $this->remaining($accessUntil))
            ->color($accessUntil === null ? 'danger' : ($this->daysLeft($accessUntil) <= 7 ? 'warning' : 'gray'));
    }

    private function onlineStat(bool $online): Stat
    {
        return Stat::make(
            __('panels.app.dashboard.subscription.menu'),
            __($online ? 'panels.app.dashboard.subscription.online' : 'panels.app.dashboard.subscription.offline'),
        )
            ->description(__($online ? 'panels.app.dashboard.subscription.online_help' : 'panels.app.dashboard.subscription.offline_help'))
            ->descriptionIcon($online ? Heroicon::OutlinedSignal : Heroicon::OutlinedSignalSlash)
            ->color($online ? 'success' : 'danger');
    }

    private function daysLeft(CarbonImmutable $date): int
    {
        return (int) ceil(CarbonImmutable::now()->diffInDays($date, false));
    }

    private function remaining(CarbonImmutable $date): string
    {
        $days = $this->daysLeft($date);

        return match (true) {
            $date->isPast() => __('panels.app.dashboard.subscription.ended'),
            $days <= 1 => __('panels.app.dashboard.subscription.ends_soon'),
            default => __('panels.app.dashboard.subscription.days_left', ['days' => $days]),
        };
    }

    /**
     * Same display rule as the subscription page: the primary market's timezone.
     */
    private function date(?CarbonImmutable $value): string
    {
        return $value === null
            ? '—'
            : $value->setTimezone('Europe/Istanbul')->locale(app()->getLocale())->isoFormat('LL');
    }
}
