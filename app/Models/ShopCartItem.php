<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One cart line of a business: a product, printed with one QR code when the
 * product is personalised. Written only by ShopCart.
 */
class ShopCartItem extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ShopProduct::class, 'shop_product_id');
    }

    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(QrCode::class);
    }

    public function total(): Money
    {
        return Money::fromMinor($this->product->price_amount * $this->quantity, $this->product->price()->currency);
    }
}
