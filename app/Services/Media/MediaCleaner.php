<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Enums\MediaType;
use App\Models\Tenant;
use App\Support\Media\TenantMediaPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Stores tenant images and removes files that are no longer referenced,
 * tied to the database transaction outcome:
 *
 * - store(): re-encodes the image (ImageNormalizer), writes it and deletes
 *   it again if any open transaction rolls back, so a failed save leaves no
 *   orphan.
 * - deleteAfterCommit(): removes a replaced or force-deleted file only once
 *   the change is committed; a rollback keeps it.
 *
 * Nothing outside the tenant's own prefix is ever deleted. Deletes are
 * idempotent; failures are logged and never break the request.
 */
final class MediaCleaner
{
    public function __construct(private readonly ImageNormalizer $images) {}

    public function store(Tenant $tenant, UploadedFile $file, MediaType $type): string
    {
        // Re-encode with gd first: EXIF/GPS removed, scaled to the type's size.
        $this->images->normalize($file, $type);

        $path = $file->storePubliclyAs(
            TenantMediaPath::directory($tenant, $type),
            TenantMediaPath::fileName($file),
            TenantMediaPath::DISK,
        );

        if ($path === false) {
            throw new \RuntimeException('The image could not be stored.');
        }

        $this->deleteOnRollback($tenant, $path);

        return $path;
    }

    public function deleteAfterCommit(Tenant $tenant, ?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        DB::afterCommit(fn () => $this->delete($tenant, $path));
    }

    /**
     * Registered on every open transaction level: an inner savepoint that
     * commits can still be undone by an outer rollback.
     */
    private function deleteOnRollback(Tenant $tenant, string $path): void
    {
        foreach (app('db.transactions')->callbackApplicableTransactions() as $transaction) {
            $transaction->addCallbackForRollback(fn () => $this->delete($tenant, $path));
        }
    }

    private function delete(Tenant $tenant, string $path): void
    {
        if (! TenantMediaPath::isOwnedBy($tenant, $path)) {
            Log::warning('Refused to delete a media file outside the tenant prefix.', [
                'tenant_id' => $tenant->getKey(),
                'path' => $path,
            ]);

            return;
        }

        try {
            Storage::disk(TenantMediaPath::DISK)->delete($path);
        } catch (Throwable $exception) {
            Log::error('Could not delete a media file.', [
                'tenant_id' => $tenant->getKey(),
                'path' => $path,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
