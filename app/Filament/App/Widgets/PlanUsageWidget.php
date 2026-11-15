<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Enums\PlanFeature;
use App\Filament\App\Pages\ViewSubscription;
use App\Filament\App\Support\PanelContext;
use App\Services\Dashboard\BusinessDashboard;
use App\Services\Subscription\FeatureGate;
use App\Tenancy\TenantContext;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Usage against the plan's count limits, read from FeatureGate (the only
 * place limits are evaluated). Information only: creation is enforced in
 * the services. Owner-only (TenantPolicy::manageSettings).
 */
class PlanUsageWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $context = app(TenantContext::class);

        return $context->check() && (PanelContext::user()?->can('manageSettings', $context->tenant()) ?? false);
    }

    protected function getHeading(): ?string
    {
        return __('panels.app.dashboard.usage.heading');
    }

    protected function getDescription(): ?string
    {
        return app(BusinessDashboard::class)->liveSubscription()?->isLive()
            ? __('panels.app.dashboard.usage.description')
            : __('panels.app.dashboard.usage.description_none');
    }

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $gate = app(FeatureGate::class);
        $tenant = app(TenantContext::class)->tenant();
        $url = ViewSubscription::getUrl();
        $stats = [];

        foreach (PlanFeature::cases() as $feature) {
            if (! $gate->tracksUsage($feature)) {
                continue;
            }

            $limit = $gate->limit($tenant, $feature);
            $used = $gate->usage($tenant, $feature);

            [$description, $color] = match (true) {
                $limit === null => [__('panels.app.dashboard.usage.unlimited'), 'success'],
                $limit === 0 => [__('panels.app.dashboard.usage.not_included'), 'danger'],
                $used >= $limit => [__('panels.app.dashboard.usage.full'), 'danger'],
                $used >= $limit * 0.8 => [__('panels.app.dashboard.usage.remaining', ['count' => $limit - $used]), 'warning'],
                default => [__('panels.app.dashboard.usage.remaining', ['count' => $limit - $used]), 'success'],
            };

            $stats[] = Stat::make($feature->label(), __('plans.usage', [
                'used' => $used,
                'limit' => $limit === null ? __('plans.unlimited') : $limit,
            ]))
                ->description($description)
                ->color($color)
                ->url($url);
        }

        return $stats;
    }
}
