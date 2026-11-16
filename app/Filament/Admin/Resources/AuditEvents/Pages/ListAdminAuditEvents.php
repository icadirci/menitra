<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AuditEvents\Pages;

use App\Filament\Admin\Resources\AuditEvents\AdminAuditEventResource;
use Filament\Resources\Pages\ListRecords;

class ListAdminAuditEvents extends ListRecords
{
    protected static string $resource = AdminAuditEventResource::class;
}
