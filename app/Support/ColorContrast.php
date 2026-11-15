<?php

declare(strict_types=1);

namespace App\Support;

/**
 * WCAG 2.x contrast ratio between two #RRGGBB colours (1.0 – 21.0).
 */
final class ColorContrast
{
    /** WCAG AA for normal-size text. */
    public const float AA = 4.5;

    public static function ratio(string $foreground, string $background): float
    {
        $a = self::luminance($foreground);
        $b = self::luminance($background);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    private static function luminance(string $hex): float
    {
        $channels = array_map(
            static function (string $pair): float {
                $value = hexdec($pair) / 255;

                return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            },
            str_split(substr(ltrim($hex, '#'), 0, 6), 2),
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
