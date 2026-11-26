<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Media\PlatformMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * Additional image of a shop product, written only by ShopProduct::syncGallery().
 */
class ShopProductImage extends Model
{
    /** @var list<string> */
    protected $fillable = ['path', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $image): void {
            if (! PlatformMedia::owns($image->path)) {
                throw new InvalidArgumentException('path must point to the shop media directory.');
            }
        });

        static::deleted(fn (self $image) => app(PlatformMedia::class)->deleteAfterCommit($image->path));
    }

    /**
     * @return BelongsTo<ShopProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(ShopProduct::class, 'shop_product_id');
    }

    public function url(): string
    {
        return PlatformMedia::url($this->path);
    }
}
