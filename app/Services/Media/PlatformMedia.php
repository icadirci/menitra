<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Enums\MediaType;
use App\Support\Media\TenantMediaPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Images of platform records (shop products) on the `media` disk:
 * platform/shop/{uuid}.{ext}, outside every tenant prefix. Same checks as
 * tenant images: sniffed MIME type, re-encoded with gd.
 */
final class PlatformMedia
{
    public const string DIRECTORY = 'platform/shop';

    public function __construct(private readonly ImageNormalizer $images) {}

    public function store(UploadedFile $file): string
    {
        $this->images->normalize($file, MediaType::Item);

        $path = $file->storePubliclyAs(self::DIRECTORY, TenantMediaPath::fileName($file), TenantMediaPath::DISK);

        return $path === false ? throw new RuntimeException('The image could not be stored.') : $path;
    }

    public function deleteAfterCommit(?string $path): void
    {
        if ($path !== null && self::owns($path)) {
            DB::afterCommit(fn () => Storage::disk(TenantMediaPath::DISK)->delete($path));
        }
    }

    public static function owns(string $path): bool
    {
        return preg_match('#^'.preg_quote(self::DIRECTORY, '#').'/[0-9a-f-]{36}\.(jpg|png|webp)$#D', $path) === 1;
    }

    public static function url(string $path): string
    {
        return Storage::disk(TenantMediaPath::DISK)->url($path);
    }
}
