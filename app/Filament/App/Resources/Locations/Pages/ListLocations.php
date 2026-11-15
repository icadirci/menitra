<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Locations\Pages;

use App\Enums\PlanFeature;
use App\Filament\App\Resources\Locations\LocationResource;
use App\Services\Subscription\FeatureGate;
use App\Tenancy\TenantContext;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLocations extends ListRecords
{
    protected static string $resource = LocationResource::class;

    public function getSubheading(): ?string
    {
        if (! (LocationResource::membership()?->isOwner() ?? false)) {
            return null;
        }

        $gate = app(FeatureGate::class);
        $tenant = app(TenantContext::class)->tenant();
        $limit = $gate->limit($tenant, PlanFeature::MaxLocations);

        return __('panels.app.locations.usage', [
            'usage' => $limit === null
                ? __('plans.unlimited')
                : __('plans.usage', ['used' => $gate->usage($tenant, PlanFeature::MaxLocations), 'limit' => $limit]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        $gate = app(FeatureGate::class);
        $tenant = app(TenantContext::class)->tenant();

        // Informational only: LocationService enforces the limit.
        return [
            CreateAction::make()
                ->disabled(fn (): bool => ! $gate->allows($tenant, PlanFeature::MaxLocations))
                ->tooltip(fn (): ?string => $gate->allows($tenant, PlanFeature::MaxLocations)
                    ? null
                    : __('panels.app.locations.limit_reached')),
        ];
    }
}
