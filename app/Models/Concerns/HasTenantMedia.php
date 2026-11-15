<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\MediaType;
use App\Exceptions\CrossTenantWriteException;
use App\Models\Tenant;
use App\Services\Media\MediaCleaner;
use App\Support\Media\TenantMediaPath;
use Illuminate\Database\Eloquent\Model;

/**
 * For models with image path columns on the `media` disk.
 *
 * - saving: a changed path must lie inside the owning tenant's directory for
 *   that media type (paths in upload fields are client-controllable).
 * - updated: a replaced or cleared file is deleted after commit.
 * - force-deleted: all files are deleted after commit; soft deletes keep them.
 *
 * New uploads are cleaned up on rollback by MediaCleaner::store().
 */
trait HasTenantMedia
{
    /**
     * @return array<string, MediaType> column => media type
     */
    abstract public function mediaAttributes(): array;

    abstract public function mediaTenant(): Tenant;

    public static function bootHasTenantMedia(): void
    {
        static::saving(function (Model $model): void {
            foreach ($model->mediaAttributes() as $attribute => $type) {
                $path = $model->getAttribute($attribute);

                if ($model->isDirty($attribute) && $path !== null
                    && ! TenantMediaPath::isOwnedBy($model->mediaTenant(), $path, $type)) {
                    throw new CrossTenantWriteException("[{$attribute}] must point to this tenant's {$type->directory()} media.");
                }
            }
        });

        static::updated(function (Model $model): void {
            foreach (array_keys($model->mediaAttributes()) as $attribute) {
                if ($model->wasChanged($attribute)) {
                    app(MediaCleaner::class)->deleteAfterCommit($model->mediaTenant(), $model->getOriginal($attribute));
                }
            }
        });

        static::deleted(function (Model $model): void {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            foreach (array_keys($model->mediaAttributes()) as $attribute) {
                app(MediaCleaner::class)->deleteAfterCommit($model->mediaTenant(), $model->getAttribute($attribute));
            }
        });
    }
}
