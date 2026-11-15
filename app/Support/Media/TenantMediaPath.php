<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Enums\MediaType;
use App\Models\Tenant;
use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The only place tenant media paths are built and checked:
 * tenants/{tenant_public_id}/{type}/{uuid}.{ext}
 */
final class TenantMediaPath
{
    public const string DISK = 'media';

    public static function directory(Tenant $tenant, MediaType $type): string
    {
        return 'tenants/'.$tenant->public_id.'/'.$type->directory();
    }

    /**
     * UUID name; the extension comes from the detected (content-based) MIME
     * type, never from the client's file name.
     *
     * @throws InvalidArgumentException
     */
    public static function fileName(UploadedFile $file): string
    {
        $extension = MediaType::MIME_EXTENSIONS[(string) self::detectMimeType($file)]
            ?? throw new InvalidArgumentException('Unsupported image type.');

        return Str::uuid()->toString().'.'.$extension;
    }

    /**
     * MIME type sniffed from the file's bytes (finfo), independent of the
     * client's name, the client's Content-Type and any framework shortcut.
     */
    public static function detectMimeType(UploadedFile $file): ?string
    {
        $path = $file->getRealPath();

        if ($path === false || ! is_readable($path)) {
            return null;
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);

        return $mime === false ? null : $mime;
    }

    /**
     * True only for a plain relative path inside the tenant's prefix (and, if
     * given, inside the type's directory).
     */
    public static function isOwnedBy(Tenant $tenant, ?string $path, ?MediaType $type = null): bool
    {
        if ($path === null || $path === '' || blank($tenant->public_id)) {
            return false;
        }

        if (str_starts_with($path, '/') || str_contains($path, '\\') || str_contains($path, "\0")
            || in_array('..', explode('/', $path), true) || in_array('.', explode('/', $path), true)) {
            return false;
        }

        $prefix = ($type === null ? 'tenants/'.$tenant->public_id : self::directory($tenant, $type)).'/';

        return str_starts_with($path, $prefix) && strlen($path) > strlen($prefix);
    }
}
