<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Role of a business membership (tenant_user.role).
 */
enum TenantRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case BranchStaff = 'branch_staff';

    /**
     * Owners reach every location of their tenant; other roles only the
     * locations assigned to their membership in location_user.
     */
    public function reachesAllLocations(): bool
    {
        return $this === self::Owner;
    }

    public function label(): string
    {
        return __("roles.tenant.{$this->value}");
    }
}
