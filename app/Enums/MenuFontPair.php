<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Curated heading/body font pairs for menu themes. Fonts are self-hosted
 * (@font-face in the theme stylesheet); the CSS font stacks come only from
 * here, never from user input. Each theme offers a subset (MenuTheme::fontPairs()).
 */
enum MenuFontPair: string
{
    case YoungSerifFigtree = 'young_serif_figtree';
    case BricolageInter = 'bricolage_inter';
    case System = 'system';

    public function heading(): string
    {
        return match ($this) {
            self::YoungSerifFigtree => "'Young Serif', Georgia, serif",
            self::BricolageInter => "'Bricolage Grotesque', ui-sans-serif, system-ui, sans-serif",
            self::System => 'Georgia, "Times New Roman", serif',
        };
    }

    public function body(): string
    {
        return match ($this) {
            self::YoungSerifFigtree => "'Figtree', ui-sans-serif, system-ui, sans-serif",
            self::BricolageInter => "'Inter', ui-sans-serif, system-ui, sans-serif",
            self::System => 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif',
        };
    }

    public function label(): string
    {
        return __("panels.app.theme.font_pairs.{$this->value}");
    }
}
