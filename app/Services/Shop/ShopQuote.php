<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Models\ShopCartItem;
use App\Support\Money;
use Illuminate\Support\Collection;

final readonly class ShopQuote
{
    /**
     * @param  Collection<int, ShopCartItem>  $items
     */
    public function __construct(
        public Collection $items,
        public Money $subtotal,
        public Money $shipping,
        public Money $total,
    ) {}

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }
}
