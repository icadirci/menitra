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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
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

    public const int MAX_GALLERY_IMAGES = 8;

    public function imageUrl(): ?string
    {
        return $this->image_path === null ? null : PlatformMedia::url($this->image_path);
    }

    /**
     * @return HasMany<ShopProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ShopProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Cover first, then the additional images.
     *
     * @return list<string>
     */
    public function imageUrls(): array
    {
        return array_values(array_filter([
            $this->imageUrl(),
            ...$this->images->map(fn (ShopProductImage $image): string => $image->url())->all(),
        ]));
    }

    /**
     * Replaces the additional images with $paths in this order. Removed rows
     * delete their file after commit; paths outside the shop directory are refused.
     *
     * @param  list<string>  $paths
     */
    public function syncGallery(array $paths): void
    {
        $paths = array_values(array_unique(array_filter($paths, 'is_string')));

        if (count($paths) > self::MAX_GALLERY_IMAGES) {
            throw new InvalidArgumentException('Too many gallery images.');
        }

        DB::transaction(function () use ($paths): void {
            $this->images()->get()
                ->reject(fn (ShopProductImage $image): bool => in_array($image->path, $paths, true))
                ->each(fn (ShopProductImage $image) => $image->delete());

            foreach ($paths as $position => $path) {
                $this->images()->updateOrCreate(['path' => $path], ['sort_order' => $position]);
            }
        });

        $this->unsetRelation('images');
    }

    /**
     * Full description with basic Markdown (bold, lists, links). Written by
     * super admins; raw HTML is stripped and unsafe links are dropped anyway.
     */
    public function descriptionHtml(): ?HtmlString
    {
        $description = trim((string) $this->translate()?->description);

        if ($description === '') {
            return null;
        }

        return new HtmlString(Str::markdown($description, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'renderer' => ['soft_break' => "<br>\n"],
        ]));
    }

    /**
     * Plain-text summary for product cards (Markdown markers removed).
     */
    public function descriptionExcerpt(int $limit = 140): ?string
    {
        $html = $this->descriptionHtml()?->toHtml();

        if ($html === null) {
            return null;
        }

        // A space before each tag keeps words of adjacent blocks apart.
        $text = Str::squish(html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES | ENT_HTML5));

        return $text === '' ? null : Str::limit($text, $limit);
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
