<?php

declare(strict_types=1);

namespace App\Support\Menu\Data;

/**
 * A branch in the tenant's location index.
 */
final readonly class LocationSummary
{
    public function __construct(
        /** Routing key, never rendered. */
        public int $id,
        public string $slug,
        public string $name,
        public ?string $address,
        public string $url,
    ) {}
}
