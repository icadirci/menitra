<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a marketing FAQ entry is shown.
 */
enum FaqPlacement: string
{
    case Home = 'home';
    case Pricing = 'pricing';

    public function label(): string
    {
        return __("panels.admin.faqs.placements.{$this->value}");
    }
}
