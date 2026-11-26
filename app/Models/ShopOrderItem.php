<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Line of a shop order; product name, price and QR code are snapshots.
 */
class ShopOrderItem extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'unit_amount' => 'integer',
            'quantity' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ShopOrder::class, 'shop_order_id');
    }

    public function total(string $currency): Money
    {
        return Money::fromMinor($this->unit_amount * $this->quantity, $currency);
    }

    /**
     * "20 × Table stand · Garden (ABC…)".
     */
    public function summary(): string
    {
        return $this->quantity.' × '.$this->product_name.($this->qr_code !== null ? ' · '.trim(($this->qr_label ?? '').' ('.$this->qr_code.')') : '');
    }
}
