<?php

declare(strict_types=1);

namespace App\Support\Menu\Data;

/**
 * A menu shown at a branch (menu switcher, location page).
 */
final readonly class MenuSummary
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public ?string $description,
        public string $type,
        public string $typeLabel,
        public string $url,
        /** @var list<HoursData> */ public array $servingHours,
    ) {}
}
