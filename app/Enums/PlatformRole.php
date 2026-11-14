<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Roles of platform owners and staff, stored on users.platform_role.
 * A platform role grants access to /admin only, never to a tenant in /app.
 */
enum PlatformRole: string
{
    case SuperAdmin = 'super_admin';
    case PlatformStaff = 'platform_staff';

    public function label(): string
    {
        return __("roles.platform.{$this->value}");
    }
}
