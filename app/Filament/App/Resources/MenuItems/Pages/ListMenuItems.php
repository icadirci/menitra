<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\MenuItems\Pages;

use App\Enums\PlanFeature;
use App\Filament\App\Resources\MenuItems\MenuItemResource;
use App\Filament\App\Support\PanelContext;
use App\Services\Subscription\FeatureGate;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMenuItems extends ListRecords
{
    protected static string $resource = MenuItemResource::class;

    protected function getHeaderActions(): array
    {
        $gate = app(FeatureGate::class);
        $tenant = PanelContext::tenant();

        return [
            CreateAction::make()
                ->disabled(fn (): bool => ! $gate->allows($tenant, PlanFeature::MaxItems))
                ->tooltip(fn (): ?string => $gate->allows($tenant, PlanFeature::MaxItems) ? null : __('panels.app.items.limit_reached')),
        ];
    }
}
