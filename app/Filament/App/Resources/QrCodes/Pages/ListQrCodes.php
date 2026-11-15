<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\QrCodes\Pages;

use App\Enums\PlanFeature;
use App\Filament\App\Resources\QrCodes\QrCodeResource;
use App\Models\QrCode;
use App\Services\Subscription\FeatureGate;
use App\Tenancy\TenantContext;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQrCodes extends ListRecords
{
    protected static string $resource = QrCodeResource::class;

    public function getSubheading(): ?string
    {
        if (! (auth()->user()?->can('create', QrCode::class) ?? false)) {
            return null;
        }

        $gate = app(FeatureGate::class);
        $tenant = app(TenantContext::class)->tenant();
        $limit = $gate->limit($tenant, PlanFeature::MaxQrCodes);

        return __('panels.app.qr_codes.usage', [
            'usage' => $limit === null
                ? __('plans.unlimited')
                : __('plans.usage', ['used' => $gate->usage($tenant, PlanFeature::MaxQrCodes), 'limit' => $limit]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        $gate = app(FeatureGate::class);
        $tenant = app(TenantContext::class)->tenant();

        // Informational only: QrCodeService enforces the limit.
        return [
            CreateAction::make()
                ->disabled(fn (): bool => ! $gate->allows($tenant, PlanFeature::MaxQrCodes))
                ->tooltip(fn (): ?string => $gate->allows($tenant, PlanFeature::MaxQrCodes)
                    ? null
                    : __('panels.app.qr_codes.limit_reached')),
        ];
    }
}
