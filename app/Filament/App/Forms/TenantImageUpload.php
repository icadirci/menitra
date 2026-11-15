<?php

declare(strict_types=1);

namespace App\Filament\App\Forms;

use App\Enums\MediaType;
use App\Services\Media\MediaCleaner;
use App\Support\Media\TenantMediaPath;
use App\Tenancy\TenantContext;
use Closure;
use Filament\Forms\Components\FileUpload;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Image upload for tenant media (`media` disk):
 * tenants/{tenant_public_id}/{type}/{uuid}.{ext}, JPEG/PNG/WebP only,
 * per-type size limit. Validation runs on the server (Livewire): MIME
 * type, client extension and the file's actual bytes (finfo).
 *
 * Files are stored through MediaCleaner::store(), inside the page's save
 * transaction (the app panel runs with databaseTransactions()), so a failed
 * save removes the new file. The model's HasTenantMedia re-checks the path
 * and removes replaced files after commit.
 */
final class TenantImageUpload
{
    public static function make(string $field, MediaType $type): FileUpload
    {
        return FileUpload::make($field)
            ->image()
            ->disk(TenantMediaPath::DISK)
            ->visibility('public')
            ->directory(fn (): string => TenantMediaPath::directory(app(TenantContext::class)->tenant(), $type))
            ->acceptedFileTypes($type->mimeTypes())
            ->maxSize($type->maxKilobytes())
            // Only the field's original path or a fresh upload is accepted, so a
            // tampered Livewire state can't reference other stored files.
            ->preventFilePathTampering()
            ->rules([
                'extensions:'.implode(',', MediaType::EXTENSIONS),
                // Content sniffing on top of Livewire's MIME check: an SVG or
                // GIF renamed to .png is rejected whatever it claims to be.
                fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($type): void {
                    if (! $value instanceof UploadedFile
                        || ! in_array(TenantMediaPath::detectMimeType($value), $type->mimeTypes(), true)) {
                        $fail(__('panels.app.media.invalid_type'));
                    }
                },
            ])
            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => app(MediaCleaner::class)
                ->store(app(TenantContext::class)->tenant(), $file, $type));
    }
}
