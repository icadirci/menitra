<?php

declare(strict_types=1);

namespace App\Support\Menu\Data;

/**
 * The tenant's active branches (shown when there is more than one).
 */
final readonly class TenantLocations
{
    public function __construct(
        public TenantBrand $brand,
        public string $locale,
        /** @var list<LocaleData> */ public array $availableLocales,
        /** @var list<LocationSummary> */ public array $locations,
        public string $version,
    ) {}
}
