<?php

declare(strict_types=1);

namespace App\Support\Menu\Data;

/**
 * Allow-listed business identity shown on menu pages.
 */
final readonly class TenantBrand
{
    public function __construct(
        public string $name,
        public ?string $tagline,
        public ?string $description,
        public ?string $logoUrl,
        public ?string $coverUrl,
        public bool $showPlatformBranding,
    ) {}
}
