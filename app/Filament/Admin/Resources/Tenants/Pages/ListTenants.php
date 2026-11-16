<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Tenants\Pages;

use App\Filament\Admin\Resources\Tenants\TenantResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Tenants are created only by registration: no create action.
 */
class ListTenants extends ListRecords
{
    protected static string $resource = TenantResource::class;
}
