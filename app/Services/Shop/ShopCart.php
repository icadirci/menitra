<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Exceptions\CrossTenantWriteException;
use App\Models\QrCode;
use App\Models\ShopCartItem;
use App\Models\ShopProduct;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The business' cart, stored in the database so that a selection made at
 * registration survives the email verification on another device. Owner-only.
 * Prices always come from the products; nothing here is an offer until
 * ShopCheckoutService creates the order.
 */
class ShopCart
{
    /** Most lines (product + QR code) one cart may hold. */
    public const int MAX_LINES = 200;

    public function __construct(
        private readonly TenantContext $context,
        private readonly ShopCatalog $catalog,
    ) {}

    /**
     * Lines that can be bought now. Lines of withdrawn products or switched
     * off codes are left out (a deleted code removes its line).
     *
     * @return Collection<int, ShopCartItem>
     */
    public function items(): Collection
    {
        if (! $this->context->check()) {
            return collect();
        }

        return ShopCartItem::query()
            ->with(['product.translations', 'qrCode'])
            ->orderBy('id')
            ->get()
            ->filter(fn (ShopCartItem $item): bool => $this->catalog->find($item->product?->public_id) !== null
                && ($item->product->is_personalized ? $item->qrCode?->is_active === true : $item->qr_code_id === null))
            ->values()
            ->toBase();
    }

    public function quantity(): int
    {
        return (int) $this->items()->sum('quantity');
    }

    public function quote(): ShopQuote
    {
        $items = $this->items();
        $subtotal = (int) $items->sum(fn (ShopCartItem $item): int => $item->total()->minor);
        $shipping = $items->isEmpty() ? 0 : $this->catalog->settings()->shippingFor($subtotal);

        return new ShopQuote($items, Money::fromMinor($subtotal), Money::fromMinor($shipping), Money::fromMinor($subtotal + $shipping));
    }

    /**
     * Adds $quantity pieces for each chosen code (personalised products) or
     * $quantity pieces (others).
     *
     * @param  list<int|string>  $qrCodeIds
     *
     * @throws AuthorizationException|ValidationException
     */
    public function add(Tenant $tenant, User $user, ShopProduct $product, int $quantity, array $qrCodeIds = []): void
    {
        $this->authorize($tenant, $user);

        if ($this->catalog->find($product->public_id) === null) {
            throw ValidationException::withMessages(['quantity' => __('shop.errors.not_available')]);
        }

        // Codes are resolved through the tenant scope; a foreign id finds nothing.
        $codes = $product->is_personalized
            ? QrCode::query()->where('is_active', true)->whereIn('id', array_map(intval(...), $qrCodeIds))->pluck('id')->all()
            : [null];

        if ($codes === []) {
            throw ValidationException::withMessages(['codes' => __('shop.errors.code_required')]);
        }

        DB::transaction(function () use ($tenant, $product, $codes, $quantity): void {
            // Serialises writes to this tenant's cart.
            Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->first();

            $lines = ShopCartItem::query()->where('shop_product_id', $product->getKey())->get();
            $max = $product->availableQuantity();

            if ($quantity < 1 || $quantity > $max || (int) $this->items()->where('shop_product_id', $product->getKey())->sum('quantity') + $quantity * count($codes) > $max) {
                throw ValidationException::withMessages(['quantity' => $max === 0
                    ? __('shop.errors.sold_out')
                    : __('shop.errors.max_quantity', ['max' => $max])]);
            }

            foreach ($codes as $code) {
                $line = $lines->first(fn (ShopCartItem $item): bool => $item->qr_code_id === $code) ?? new ShopCartItem;
                $line->forceFill([
                    'shop_product_id' => $product->getKey(),
                    'qr_code_id' => $code,
                    'quantity' => (int) $line->quantity + $quantity,
                ])->save();
            }

            if (ShopCartItem::query()->count() > self::MAX_LINES) {
                throw ValidationException::withMessages(['codes' => __('shop.errors.too_many_lines', ['max' => self::MAX_LINES])]);
            }
        });
    }

    /**
     * @throws AuthorizationException
     */
    public function remove(Tenant $tenant, User $user, int $itemId): void
    {
        $this->authorize($tenant, $user);

        ShopCartItem::query()->whereKey($itemId)->delete();
    }

    /**
     * Products picked on the registration form: [public_id => quantity],
     * printed with the business' first QR code (changeable in the cart).
     * Visitor input: anything unknown or out of range is skipped.
     */
    public function seed(Tenant $tenant, User $user, mixed $selection): void
    {
        if (! is_array($selection)) {
            return;
        }

        $code = QrCode::query()->where('is_active', true)->orderBy('id')->value('id');

        foreach ($this->catalog->products() as $product) {
            $quantity = filter_var($selection[$product->public_id] ?? null, FILTER_VALIDATE_INT);

            if ($quantity === false || $quantity < 1) {
                continue;
            }

            try {
                $this->add($tenant, $user, $product, $quantity, $code === null ? [] : [$code]);
            } catch (ValidationException) {
                continue;
            }
        }
    }

    private function authorize(Tenant $tenant, User $user): void
    {
        if (! $this->context->check() || $this->context->id() !== (int) $tenant->getKey()) {
            throw new CrossTenantWriteException('A cart can only be changed for the tenant in the current TenantContext.');
        }

        if (! $user->can('manageSettings', $tenant)) {
            throw new AuthorizationException;
        }
    }
}
