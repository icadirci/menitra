<?php

declare(strict_types=1);

namespace App\Filament\Shared;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Default avatar for users and tenants without an image: their initials on a
 * colour derived from the record, rendered as an inline SVG data URI.
 *
 * Replaces Filament's UiAvatarsProvider, which loads ui-avatars.com: that is
 * blocked by our CSP (img-src allows only self, data: and the media disk) and
 * would send names to a third party.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    /**
     * Background colours, all with white text above WCAG AA contrast (≥ 4.5:1).
     */
    private const PALETTE = [
        '#1F4D3B', // forest
        '#2563A8', // blue
        '#7C3AED', // violet
        '#B4235A', // raspberry
        '#B03A2E', // brick
        '#9A4A06', // amber
        '#0F6E6E', // teal
        '#4F46E5', // indigo
        '#6B4F2A', // walnut
        '#475569', // slate
    ];

    public function get(Model|Authenticatable $record): string
    {
        $name = (string) Filament::getNameForDefaultAvatar($record);

        return self::dataUri(self::initials($name), self::colour($record, $name));
    }

    /**
     * Up to two initials: first letter of the first and the last word ("İbrahim Halil Çadırcı" → "İÇ").
     */
    public static function initials(string $name): string
    {
        $words = array_values(array_filter(array_map(
            // Skip leading punctuation, e.g. "[SYSTEM] Admin".
            fn (string $word): string => (string) preg_replace('/^[^\p{L}\p{N}]+/u', '', $word),
            preg_split('/\s+/u', trim($name)) ?: [],
        ), fn (string $word): bool => $word !== ''));

        if ($words === []) {
            return '?';
        }

        $letters = [mb_substr($words[0], 0, 1)];

        if (count($words) > 1) {
            $letters[] = mb_substr($words[array_key_last($words)], 0, 1);
        }

        return mb_strtoupper(implode('', $letters), 'UTF-8');
    }

    /**
     * Stable per record (type + key), so renaming doesn't change the colour.
     */
    public static function colour(Model|Authenticatable $record, string $name = ''): string
    {
        $key = $record instanceof Model ? $record->getMorphClass().':'.$record->getKey() : $name;

        return self::PALETTE[crc32((string) $key) % count(self::PALETTE)];
    }

    private static function dataUri(string $initials, string $background): string
    {
        $text = htmlspecialchars($initials, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="'.$background.'"/>'
            .'<text x="50%" y="50%" dy=".35em" text-anchor="middle" fill="#FFFFFF" '
            .'font-family="system-ui,-apple-system,Segoe UI,Roboto,sans-serif" font-size="26" font-weight="600">'
            .$text.'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
