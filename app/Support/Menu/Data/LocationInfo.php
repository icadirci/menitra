<?php

declare(strict_types=1);

namespace App\Support\Menu\Data;

/**
 * Allow-listed branch details. Hours are raw facts; LiveMenuState decides "open now".
 */
final readonly class LocationInfo
{
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $description,
        public ?string $address,
        public ?string $phone,
        public ?string $whatsapp,
        public ?string $email,
        public ?string $mapUrl,
        public string $timezone,
        public ?string $coverUrl,
        public ?string $theme,
        public string $url,
        /** @var list<HoursData> */ public array $weeklyHours,
        /** @var list<SpecialHoursData> */ public array $specialHours,
    ) {}
}
