<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kinds of tenant images. Each kind has its own directory under
 * tenants/{tenant_public_id}/ and its own size limit. Only JPEG, PNG and
 * WebP are accepted (no SVG: script risk; no GIF).
 */
enum MediaType: string
{
    case Logo = 'logo';
    case Cover = 'cover';
    case Location = 'locations';
    case Category = 'categories';
    case Item = 'items';

    /** MIME type => stored extension. */
    public const array MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /** Client extensions accepted next to the MIME check. */
    public const array EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function directory(): string
    {
        return $this->value;
    }

    /**
     * @return list<string>
     */
    public function mimeTypes(): array
    {
        return array_keys(self::MIME_EXTENSIONS);
    }

    /**
     * Longest edge in pixels after upload (ImageNormalizer scales larger
     * images down; smaller ones keep their size).
     */
    public function maxDimension(): int
    {
        return match ($this) {
            self::Logo => 512,
            self::Cover => 2000,
            self::Location => 1600,
            self::Category, self::Item => 1200,
        };
    }

    public function maxKilobytes(): int
    {
        return match ($this) {
            self::Logo => 2048,
            default => 5120,
        };
    }
}
