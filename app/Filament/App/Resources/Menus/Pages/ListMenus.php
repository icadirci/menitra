<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Menus\Pages;

use App\Enums\PlanFeature;
use App\Filament\App\Resources\Menus\MenuResource;
use App\Filament\App\Support\PanelContext;
use App\Services\Subscription\FeatureGate;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMenus extends ListRecords
{
    protected static string $resource = MenuResource::class;

    protected function getHeaderActions(): array
    {
        $gate = app(FeatureGate::class);
        $tenant = PanelContext::tenant();

        // Informational only: MenuService enforces the limit.
        return [
            CreateAction::make()
                ->disabled(fn (): bool => ! $gate->allows($tenant, PlanFeature::MaxMenus))
                ->tooltip(fn (): ?string => $gate->allows($tenant, PlanFeature::MaxMenus) ? null : __('panels.app.menus.limit_reached')),
        ];
    }
}
