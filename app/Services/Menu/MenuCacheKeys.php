<?php

declare(strict_types=1);

namespace App\Services\Menu;

/**
 * The only place public menu cache keys are built (docs/architecture/caching.md).
 * Every key holding tenant data contains the tenant id.
 */
final class MenuCacheKeys
{
    public const string PREFIX = 'qrmenu:';

    public static function host(string $host): string
    {
        return self::PREFIX.'host:'.strtolower($host);
    }

    public static function slug(string $slug): string
    {
        return self::PREFIX.'slug:'.strtolower($slug);
    }

    public static function qr(string $code): string
    {
        return self::PREFIX.'qr:'.$code;
    }

    public static function tenantVersion(int $tenantId): string
    {
        return self::PREFIX."tenant:{$tenantId}:version";
    }

    public static function locationVersion(int $tenantId, int $locationId): string
    {
        return self::PREFIX."tenant:{$tenantId}:location:{$locationId}:version";
    }

    public static function menuSnapshot(int $tenantId, int $locationId, int $menuId, string $locale, int $tenantVersion, int $locationVersion): string
    {
        return self::PREFIX."tenant:{$tenantId}:location:{$locationId}:menu:{$menuId}:{$locale}:v{$tenantVersion}.{$locationVersion}";
    }

    public static function locationIndex(int $tenantId, int $locationId, string $locale, int $tenantVersion, int $locationVersion): string
    {
        return self::PREFIX."tenant:{$tenantId}:location:{$locationId}:index:{$locale}:v{$tenantVersion}.{$locationVersion}";
    }

    public static function tenantLocations(int $tenantId, string $locale, int $tenantVersion): string
    {
        return self::PREFIX."tenant:{$tenantId}:locations:{$locale}:v{$tenantVersion}";
    }
}
