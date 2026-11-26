<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ShopOrderStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Tenant-owned order of shop products. Written only by ShopCheckoutService
 * and ShopOrderService, so nothing is mass-assignable. The delivery address
 * is personal data: encrypted and hidden from serialization.
 */
class ShopOrder extends Model
{
    use BelongsToTenant, HasUlids;

    /** @var list<string> */
    protected $fillable = [];

    /** @var list<string> */
    protected $hidden = ['delivery_address', 'contact_email', 'customer_note'];

    protected function casts(): array
    {
        return [
            'status' => ShopOrderStatus::class,
            'subtotal_amount' => 'integer',
            'shipping_amount' => 'integer',
            'total_amount' => 'integer',
            'delivery_address' => 'encrypted:array',
            'paid_at' => 'immutable_datetime',
            'shipped_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'refunded_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShopOrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function subtotal(): Money
    {
        return Money::fromMinor($this->subtotal_amount, $this->currency);
    }

    public function shipping(): Money
    {
        return Money::fromMinor($this->shipping_amount, $this->currency);
    }

    public function total(): Money
    {
        return Money::fromMinor($this->total_amount, $this->currency);
    }

    /**
     * @return list<string> recipient, phone, street, district / city
     */
    public function addressLines(): array
    {
        $address = $this->delivery_address ?? [];

        return array_values(array_filter([
            $address['recipient'] ?? null,
            $address['phone'] ?? null,
            $address['address'] ?? null,
            trim(implode(' ', array_filter([$address['postal_code'] ?? null, implode(' / ', array_filter([$address['district'] ?? null, $address['city'] ?? null]))]))),
        ]));
    }
}
