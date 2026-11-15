<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Social profiles of the platform shown on the marketing site. Each case maps
 * to a `{value}_url` column on site_settings and an icon of the same name.
 */
enum SocialNetwork: string
{
    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case X = 'x';
    case Linkedin = 'linkedin';
    case Youtube = 'youtube';
    case Tiktok = 'tiktok';

    public function column(): string
    {
        return "{$this->value}_url";
    }

    /**
     * Brand names are not translated.
     */
    public function label(): string
    {
        return match ($this) {
            self::Instagram => 'Instagram',
            self::Facebook => 'Facebook',
            self::X => 'X',
            self::Linkedin => 'LinkedIn',
            self::Youtube => 'YouTube',
            self::Tiktok => 'TikTok',
        };
    }
}
