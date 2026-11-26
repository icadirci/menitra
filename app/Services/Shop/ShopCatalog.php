<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Models\ShopProduct;
use App\Models\ShopSetting;
use Illuminate\Database\Eloquent\Collection;

/**
 * What is on sale now (platform data). Scoped singleton, memoised per request.
 */
class ShopCatalog
{
    private ?ShopSetting $settings = null;

    /** @var Collection<int, ShopProduct>|null */
    private ?Collection $products = null;

    public function settings(): ShopSetting
    {
        return $this->settings ??= ShopSetting::current();
    }

    /**
     * Open = switched on in /admin and at least one active product.
     */
    public function isOpen(): bool
    {
        return $this->products()->isNotEmpty();
    }

    /**
     * Active products in display order; empty while the shop is closed.
     *
     * @return Collection<int, ShopProduct>
     */
    public function products(): Collection
    {
        return $this->products ??= $this->settings()->is_enabled
            ? ShopProduct::query()->active()->with('translations')->orderBy('sort_order')->orderBy('id')->get()
            : new Collection;
    }

    public function find(mixed $publicId): ?ShopProduct
    {
        return is_string($publicId) ? $this->products()->firstWhere('public_id', $publicId) : null;
    }
}
