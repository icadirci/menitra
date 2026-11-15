<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Printable backgrounds a QR code can be placed on (download only, never
 * stored). The images are platform assets in resources/images/qr-backgrounds/
 * and each one has a white rounded box the code is drawn into; frame() is
 * that box in image pixels (x, y, width, height), measured once from the file.
 */
enum QrBackground: string
{
    case CreamPortrait = 'cream-portrait';
    case WoodPortrait = 'wood-portrait';
    case EmeraldPortrait = 'emerald-portrait';
    case TerracePortrait = 'terrace-portrait';
    case EmeraldLandscape = 'emerald-landscape';
    case CreamLandscape = 'cream-landscape';
    case PatisserieLandscape = 'patisserie-landscape';
    case WoodLandscape = 'wood-landscape';

    public function path(): string
    {
        return resource_path("images/qr-backgrounds/{$this->value}.png");
    }

    /**
     * @return array{int, int, int, int} x, y, width, height of the white box
     */
    public function frame(): array
    {
        return match ($this) {
            self::CreamPortrait => [292, 470, 500, 483],
            self::WoodPortrait => [265, 397, 607, 572],
            self::EmeraldPortrait => [276, 551, 530, 523],
            self::TerracePortrait => [276, 432, 466, 453],
            self::EmeraldLandscape => [831, 218, 537, 518],
            self::CreamLandscape => [804, 246, 516, 490],
            self::PatisserieLandscape => [752, 206, 541, 530],
            self::WoodLandscape => [821, 209, 526, 524],
        };
    }

    public function label(): string
    {
        return __("panels.app.qr_codes.backgrounds.{$this->value}");
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
