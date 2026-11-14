<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Tenancy\TenantContext;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getTitle(): string
    {
        return __('panels.app.dashboard.title');
    }

    public function getSubheading(): ?string
    {
        $context = app(TenantContext::class);

        return $context->check() ? $context->tenant()->getFilamentName() : null;
    }
}
