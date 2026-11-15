<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Enums\MediaType;
use App\Support\Media\TenantMediaPath;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Re-encodes an uploaded tenant image with the bundled gd extension before
 * it is stored (MediaCleaner::store()): applies the JPEG EXIF orientation,
 * scales it down to the type's longest edge (MediaType::maxDimension()) and
 * writes it again in the same format. Re-encoding drops every metadata block
 * (EXIF incl. GPS, comments, embedded payloads); the format never changes, so
 * the stored extension (from the sniffed MIME type) stays correct.
 *
 * Pixel count is capped (MAX_PIXELS) by TenantImageUpload's validation so a
 * small file can't decompress into an image that exhausts PHP's memory. When
 * gd can't decode a file that passed validation (e.g. an animated WebP), the
 * original is kept and a warning is logged.
 */
final class ImageNormalizer
{
    /** 4096 × 4096: about 64 MB as a gd true-colour image (FPM memory_limit 128M). */
    public const int MAX_PIXELS = 16_777_216;

    private const int JPEG_QUALITY = 82;

    private const int WEBP_QUALITY = 80;

    private const int PNG_COMPRESSION = 6;

    public static function withinPixelLimit(UploadedFile $file): bool
    {
        $path = $file->getRealPath();

        return $path !== false && self::pixelsAllowed($path);
    }

    private static function pixelsAllowed(string $path): bool
    {
        $size = @getimagesize($path);

        return $size !== false && $size[0] > 0 && $size[1] > 0 && $size[0] * $size[1] <= self::MAX_PIXELS;
    }

    public function normalize(UploadedFile $file, MediaType $type): void
    {
        $path = $file->getRealPath();
        $mime = TenantMediaPath::detectMimeType($file);

        if ($path === false || ! is_writable($path) || ! in_array($mime, $type->mimeTypes(), true)) {
            return;
        }

        try {
            $image = $this->load($path, $mime);

            if ($image === null) {
                Log::warning('Image kept as uploaded: gd could not decode it.', ['mime' => $mime, 'type' => $type->value]);

                return;
            }

            $image = $this->scale($this->orient($image, $path, $mime), $type->maxDimension());
            $this->write($image, $path, $mime);
        } catch (Throwable $exception) {
            Log::warning('Image kept as uploaded: re-encoding failed.', ['mime' => $mime, 'error' => $exception->getMessage()]);
        }
    }

    private function load(string $path, string $mime): ?GdImage
    {
        if (! self::pixelsAllowed($path)) {
            return null;
        }

        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };

        return $image instanceof GdImage ? $image : null;
    }

    /**
     * Phones store the rotation as EXIF; re-encoding drops EXIF, so it is
     * applied to the pixels first.
     */
    private function orient(GdImage $image, string $path, string $mime): GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated instanceof GdImage ? $rotated : $image;
    }

    private function scale(GdImage $image, int $maxDimension): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if (max($width, $height) <= $maxDimension) {
            return $image;
        }

        $ratio = $maxDimension / max($width, $height);
        $scaled = imagescale($image, max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio)), IMG_BICUBIC);

        return $scaled instanceof GdImage ? $scaled : $image;
    }

    private function write(GdImage $image, string $path, string $mime): void
    {
        $temporary = $path.'.normalized';

        if ($mime !== 'image/jpeg') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        $written = match ($mime) {
            'image/jpeg' => imagejpeg($image, $temporary, self::JPEG_QUALITY),
            'image/png' => imagepng($image, $temporary, self::PNG_COMPRESSION),
            'image/webp' => imagewebp($image, $temporary, self::WEBP_QUALITY),
            default => false,
        };

        if ($written && filesize($temporary) > 0) {
            rename($temporary, $path);
            clearstatcache(true, $path);
        } else {
            @unlink($temporary);
        }
    }
}
