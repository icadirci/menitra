<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Translations\ShopProductTranslation;
use App\Services\Media\PlatformMedia;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Physical product sold to businesses (platform data), managed in /admin.
 * Never deleted: order lines reference it; deactivate instead.
 */
class ShopProduct extends Model
{
    use HasTranslations, HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'price_amount',
        'image_path',
        'is_personalized',
        'stock_quantity',
        'max_per_order',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_amount' => 'integer',
            'is_personalized' => 'boolean',
            'stock_quantity' => 'integer',
            'max_per_order' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $product): void {
            if ($product->isDirty('image_path') && $product->image_path !== null && ! PlatformMedia::owns($product->image_path)) {
                throw new InvalidArgumentException('image_path must point to the shop media directory.');
            }
        });

        static::updated(function (self $product): void {
            if ($product->wasChanged('image_path')) {
                app(PlatformMedia::class)->deleteAfterCommit($product->getOriginal('image_path'));
            }
        });
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function price(): Money
    {
        return Money::fromMinor($this->price_amount, $this->currency ?? 'TRY');
    }

    public function imageUrl(): ?string
    {
        return $this->image_path === null ? null : PlatformMedia::url($this->image_path);
    }

    /**
     * The most of this product one order may contain now (0 = sold out).
     */
    public function availableQuantity(): int
    {
        return $this->stock_quantity === null
            ? $this->max_per_order
            : max(0, min($this->max_per_order, $this->stock_quantity));
    }

    protected function translationModel(): string
    {
        return ShopProductTranslation::class;
    }

    protected function translationFallbackLocale(): ?string
    {
        return config('locales.default');
    }
}
