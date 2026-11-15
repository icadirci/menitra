<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Theme registry (docs/architecture/themes.md). Themes are code: Blade views
 * in resources/views/themes/{key}/ and one stylesheet each.
 */
enum MenuTheme: string
{
    case Classic = 'classic';
    case Minimal = 'minimal';
    case Bistro = 'bistro';
    case Cafe = 'cafe';
    case Grid = 'grid';

    public function view(): string
    {
        return "themes.{$this->value}.menu";
    }

    public function locationView(): string
    {
        return "themes.{$this->value}.location";
    }

    public function stylesheet(): string
    {
        return "resources/css/themes/{$this->value}.css";
    }

    /**
     * Default settings, rendered as CSS custom properties. Tenant settings
     * (theme_settings, B9) are merged over these.
     *
     * @return array{primary: string, accent: string, bg: string, surface: string, text: string, muted: string, ornament: string}
     */
    public function defaults(): array
    {
        return match ($this) {
            // Design reference: MenuKonak / DetailKonak.
            self::Classic => [
                'primary' => '#23443A', 'accent' => '#9C4A2F', 'bg' => '#F4EBDD', 'surface' => '#FBF5EA',
                'text' => '#2B1D16', 'muted' => '#6B5446', 'ornament' => '#C49A45',
            ],
            default => [
                'primary' => '#1F4D3B', 'accent' => '#E7A33E', 'bg' => '#FAF7F2', 'surface' => '#FFFFFF',
                'text' => '#1C1917', 'muted' => '#57534E', 'ornament' => '#E7A33E',
            ],
        };
    }

    /**
     * @return array<string, list<string>>
     */
    public function settingsRules(): array
    {
        return array_map(fn (): array => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], $this->defaults());
    }

    public function requiredFeature(): ?PlanFeature
    {
        return $this === self::Classic ? null : PlanFeature::PremiumThemes;
    }
}
