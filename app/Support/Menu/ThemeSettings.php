<?php

declare(strict_types=1);

namespace App\Support\Menu;

use App\Enums\MenuFontPair;
use App\Enums\MenuTheme;

/**
 * Validated presentation settings of the active theme: hex colours and the
 * curated font pair are exposed as CSS custom properties (no per-tenant CSS
 * builds); layout options are read by the theme's views via option().
 */
final readonly class ThemeSettings
{
    /**
     * @param  array<string, string>  $colors
     * @param  array<string, bool|string>  $layout
     */
    public function __construct(
        public MenuTheme $theme,
        public array $colors,
        public ?MenuFontPair $fontPair = null,
        public array $layout = [],
    ) {}

    public static function defaults(MenuTheme $theme): self
    {
        return self::fromArray($theme, $theme->defaults());
    }

    /**
     * Saved settings (theme_settings.settings) merged over the theme's
     * defaults and validated again: a section that no longer passes the
     * current rules (colours incl. contrast, font pair, a layout option)
     * falls back to its defaults instead of reaching the page.
     *
     * @param  array<string, mixed>|null  $stored
     */
    public static function fromStored(MenuTheme $theme, ?array $stored): self
    {
        return self::fromArray($theme, self::merge($theme, $stored ?? []));
    }

    /**
     * The merged, valid settings as an array (form state of the theme page).
     *
     * @param  array<string, mixed>|null  $stored
     * @return array{colors: array<string, string>, font_pair: string, layout: array<string, bool|string>}
     */
    public static function merge(MenuTheme $theme, ?array $stored): array
    {
        $defaults = $theme->defaults();
        $stored ??= [];

        $settings = [
            'colors' => array_merge($defaults['colors'], array_intersect_key((array) ($stored['colors'] ?? []), $defaults['colors'])),
            'font_pair' => $stored['font_pair'] ?? $defaults['font_pair'],
            'layout' => array_merge($defaults['layout'], array_intersect_key((array) ($stored['layout'] ?? []), $defaults['layout'])),
        ];

        $errors = $theme->validator($settings)->errors();

        if ($errors->isEmpty()) {
            return $theme->normalize($settings);
        }

        if ($errors->has('colors') || $errors->has('colors.*')) {
            $settings['colors'] = $defaults['colors'];
        }

        if ($errors->has('font_pair')) {
            $settings['font_pair'] = $defaults['font_pair'];
        }

        foreach (array_keys($defaults['layout']) as $name) {
            if ($errors->has("layout.{$name}")) {
                $settings['layout'][$name] = $defaults['layout'][$name];
            }
        }

        return $theme->normalize($settings);
    }

    /**
     * Value of a layout option (null when the theme has no such option).
     */
    public function option(string $name): bool|string|null
    {
        return $this->layout[$name] ?? null;
    }

    /**
     * Categories as a grid of cards (layout option `category_layout` = `tiles`)
     * instead of one long list.
     */
    public function categoryTiles(): bool
    {
        return $this->option('category_layout') === 'tiles';
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

        if ($this->fontPair !== null) {
            $css .= "--menu-font-heading:{$this->fontPair->heading()};--menu-font-body:{$this->fontPair->body()};";
        }

        return $css;
    }

    public function key(): string
    {
        return $this->theme->value;
    }

    /**
     * @param  array{colors: array<string, string>, font_pair: string, layout: array<string, bool|string>}  $settings
     */
    private static function fromArray(MenuTheme $theme, array $settings): self
    {
        return new self($theme, $settings['colors'], MenuFontPair::tryFrom($settings['font_pair']), $settings['layout']);
    }
}
