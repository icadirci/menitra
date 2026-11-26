<?php

declare(strict_types=1);

namespace App\Filament\Admin\Forms;

use App\Enums\MediaType;
use App\Services\Media\ImageNormalizer;
use App\Services\Media\PlatformMedia;
use App\Support\Media\TenantMediaPath;
use Closure;
use Filament\Forms\Components\FileUpload;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Image of a platform record: the checks of TenantImageUpload (content
 * sniffing, pixel limit, no path tampering), stored through PlatformMedia.
 */
final class PlatformImageUpload
{
    public static function make(string $field): FileUpload
    {
        $type = MediaType::Item;

        return FileUpload::make($field)
            ->image()
            ->disk(TenantMediaPath::DISK)
            ->visibility('public')
            ->directory(PlatformMedia::DIRECTORY)
            ->acceptedFileTypes($type->mimeTypes())
            ->maxSize($type->maxKilobytes())
            ->preventFilePathTampering()
            ->rules([
                'extensions:'.implode(',', MediaType::EXTENSIONS),
                fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($type): void {
                    if (! $value instanceof UploadedFile || ! in_array(TenantMediaPath::detectMimeType($value), $type->mimeTypes(), true)) {
                        $fail(__('panels.app.media.invalid_type'));
                    } elseif (! ImageNormalizer::withinPixelLimit($value)) {
                        $fail(__('panels.app.media.too_many_pixels', ['max' => '4096 × 4096']));
                    }
                },
            ])
            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => app(PlatformMedia::class)->store($file));
    }
}
