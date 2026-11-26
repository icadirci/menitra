<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * Shop-wide settings (platform data, single row id = 1), edited in /admin by
 * super admins (ShopSettingPolicy).
 */
class ShopSetting extends Model
{
    public const SINGLETON_ID = 1;

    /** @var list<string> */
    protected $fillable = [
        'is_enabled',
        'shipping_amount',
        'free_shipping_from',
        'min_delivery_days',
        'max_delivery_days',
        'notification_email',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_enabled' => false,
        'shipping_amount' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'shipping_amount' => 'integer',
            'free_shipping_from' => 'integer',
            'min_delivery_days' => 'integer',
            'max_delivery_days' => 'integer',
        ];
    }

    /**
     * The settings row, or the unsaved defaults (shop closed).
     */
    public static function current(): self
    {
        return static::query()->find(self::SINGLETON_ID) ?? (new self)->forceFill(['id' => self::SINGLETON_ID]);
    }

    /**
     * Shipping for a cart: free from the threshold on.
     */
    public function shippingFor(int $subtotal): int
    {
        return $this->free_shipping_from !== null && $subtotal >= $this->free_shipping_from ? 0 : $this->shipping_amount;
    }

    public function freeShippingFrom(): ?Money
    {
        return $this->free_shipping_from === null ? null : Money::fromMinor($this->free_shipping_from);
    }

    /**
     * "3–5 business days", or null when not set.
     */
    public function deliveryText(): ?string
    {
        $min = $this->min_delivery_days ?? $this->max_delivery_days;
        $max = $this->max_delivery_days ?? $this->min_delivery_days;

        return match (true) {
            $min === null => null,
            $min === $max => __('shop.delivery_days_exact', ['days' => $min]),
            default => __('shop.delivery_days', ['min' => $min, 'max' => $max]),
        };
    }
}
