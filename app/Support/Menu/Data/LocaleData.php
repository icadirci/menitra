<?php

declare(strict_types=1);

namespace App\Support\Menu\Data;

/**
 * An enabled locale for the language switcher.
 */
final readonly class LocaleData
{
    public function __construct(
        public string $code,
        public string $label,
    ) {}
}
