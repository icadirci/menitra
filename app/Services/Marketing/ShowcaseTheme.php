<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Enums\MenuTheme;

/**
 * One theme of the showcase page. $venue / $url are null while the theme's
 * demo business is missing or offline (then no live link is shown).
 */
final readonly class ShowcaseTheme
{
    /**
     * @param  array<string, string>  $colors  the theme's default palette (code, not tenant data)
     */
    public function __construct(
        public MenuTheme $theme,
        public array $colors,
        public ?string $venue,
        public ?string $url,
    ) {}
}
