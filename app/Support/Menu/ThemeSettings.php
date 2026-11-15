<?php

declare(strict_types=1);

namespace App\Support\Menu;

use App\Enums\MenuTheme;

/**
 * Validated presentation settings of the active theme. Values are hex colours
 * only, exposed as CSS custom properties (no per-tenant CSS builds).
 */
final readonly class ThemeSettings
{
    /**
     * @param  array<string, string>  $colors
     */
    public function __construct(
        public MenuTheme $theme,
        public array $colors,
    ) {}

    public static function defaults(MenuTheme $theme): self
    {
        return new self($theme, $theme->defaults());
    }

    /**
     * CSS declarations for the menu root element, e.g. "--menu-primary:#23443A;".
     */
    public function cssVariables(): string
    {
        $css = '';

        foreach ($this->colors as $name => $value) {
            if (preg_match('/^[a-z]+$/', $name) === 1 && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1) {
                $css .= "--menu-{$name}:{$value};";
            }
        }

        return $css;
    }

    public function key(): string
    {
        return $this->theme->value;
    }
}
