<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Enums\ShopOrderStatus;
use App\Exceptions\CrossTenantWriteException;
use App\Filament\App\Pages\ShopOrders;
use App\Mail\NewShopOrderMail;
use App\Mail\ShopOrderMail;
use App\Models\ShopCartItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopProduct;
use App\Models\ShopSetting;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * The only place an order's status changes. Runs inside the order's own
 * TenantContext; authorization is the caller's job (PaymentProcessor after a
 * verified payment, ShopOrderAdminService in /admin).
 */
class ShopOrderService
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * A verified payment arrived (also for an order whose checkout had failed
     * or expired): stock is taken, the bought lines leave the cart.
     */
    public function markPaid(ShopOrder $order, CarbonImmutable $paidAt): ShopOrder
    {
        return $this->transition($order, ShopOrderStatus::Paid, function (ShopOrder $order) use ($paidAt): void {
            $order->paid_at = $paidAt;

            foreach ($this->quantities($order) as $productId => $quantity) {
                ShopProduct::query()->whereKey($productId)->whereNotNull('stock_quantity')->decrement('stock_quantity', $quantity);
            }

            if (ShopProduct::query()->whereKey(array_keys($this->quantities($order)))->where('stock_quantity', '<', 0)->exists()) {
                Log::warning('Shop stock is below zero after a paid order.', ['order' => $order->public_id]);
            }

            foreach ($order->items()->get() as $item) {
                ShopCartItem::query()
                    ->where('shop_product_id', $item->shop_product_id)
                    ->where('qr_code_id', $item->qr_code_id)
                    ->where('created_at', '<=', $order->created_at)
                    ->delete();
            }

            $this->mailCustomer($order, ShopOrderStatus::Paid);
            $this->mailPlatform($order);
        });
    }

    public function markPaymentFailed(ShopOrder $order): void
    {
        if ($order->status === ShopOrderStatus::AwaitingPayment) {
            $this->transition($order, ShopOrderStatus::PaymentFailed);
        }
    }

    public function prepare(ShopOrder $order): ShopOrder
    {
        return $this->transition($order, ShopOrderStatus::Preparing);
    }

    public function ship(ShopOrder $order, string $carrier, ?string $trackingNumber, ?string $trackingUrl): ShopOrder
    {
        if (trim($carrier) === '') {
            throw ValidationException::withMessages(['carrier' => __('validation.required', ['attribute' => __('shop.admin.orders.carrier')])]);
        }

        if ($trackingUrl !== null && (! str_starts_with($trackingUrl, 'https://') || filter_var($trackingUrl, FILTER_VALIDATE_URL) === false)) {
            throw ValidationException::withMessages(['tracking_url' => __('validation.url', ['attribute' => __('shop.admin.orders.tracking_url')])]);
        }

        return $this->transition($order, ShopOrderStatus::Shipped, function (ShopOrder $order) use ($carrier, $trackingNumber, $trackingUrl): void {
            $order->carrier = trim($carrier);
            $order->tracking_number = $trackingNumber;
            $order->tracking_url = $trackingUrl;
            $order->shipped_at = CarbonImmutable::now();
            $this->mailCustomer($order, ShopOrderStatus::Shipped);
        });
    }

    public function deliver(ShopOrder $order): ShopOrder
    {
        return $this->transition($order, ShopOrderStatus::Delivered, function (ShopOrder $order): void {
            $order->delivered_at = CarbonImmutable::now();
        });
    }

    /**
     * Before shipping only. Puts tracked stock back; the money is refunded in
     * the iyzico merchant panel and then recorded with markRefunded().
     */
    public function cancel(ShopOrder $order, string $reason): ShopOrder
    {
        return $this->transition($order, ShopOrderStatus::Cancelled, function (ShopOrder $order) use ($reason): void {
            $order->cancel_reason = mb_substr(trim($reason), 0, 500);
            $order->cancelled_at = CarbonImmutable::now();

            foreach ($this->quantities($order) as $productId => $quantity) {
                ShopProduct::query()->whereKey($productId)->whereNotNull('stock_quantity')->increment('stock_quantity', $quantity);
            }

            $this->mailCustomer($order, ShopOrderStatus::Cancelled);
        });
    }

    /**
     * Records a refund already made in the iyzico merchant panel.
     */
    public function markRefunded(ShopOrder $order): ShopOrder
    {
        $this->ensureCurrentTenant($order);

        return DB::transaction(function () use ($order): ShopOrder {
            $locked = ShopOrder::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== ShopOrderStatus::Cancelled || $locked->refunded_at !== null) {
                throw ValidationException::withMessages(['status' => __('shop.errors.transition')]);
            }

            $locked->forceFill(['refunded_at' => CarbonImmutable::now()])->save();

            return $locked;
        });
    }

    /**
     * @param  (callable(ShopOrder): void)|null  $apply
     */
    private function transition(ShopOrder $order, ShopOrderStatus $to, ?callable $apply = null): ShopOrder
    {
        $this->ensureCurrentTenant($order);

        return DB::transaction(function () use ($order, $to, $apply): ShopOrder {
            $locked = ShopOrder::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->status->canBecome($to)) {
                throw ValidationException::withMessages(['status' => __('shop.errors.transition')]);
            }

            $locked->status = $to;

            if ($apply !== null) {
                $apply($locked);
            }

            $locked->save();

            return $locked;
        });
    }

    /**
     * @return array<int, int> product id => pieces
     */
    private function quantities(ShopOrder $order): array
    {
        return $order->items()->orderBy('shop_product_id')->get()
            ->groupBy('shop_product_id')
            ->map(fn ($items): int => (int) $items->sum('quantity'))
            ->all();
    }

    private function mailCustomer(ShopOrder $order, ShopOrderStatus $status): void
    {
        $tenant = $this->context->tenant();
        $locale = $tenant->default_locale;
        $mail = new ShopOrderMail(
            status: $status,
            orderNumber: (string) $order->order_number,
            businessName: $this->businessName($tenant),
            lines: $order->items()->orderBy('id')->get()->map(fn (ShopOrderItem $item): string => $item->summary())->all(),
            totalMinor: $order->total_amount,
            currency: $order->currency,
            url: ShopOrders::getUrl(panel: 'app', tenant: $tenant),
            carrier: $order->carrier,
            trackingNumber: $order->tracking_number,
            trackingUrl: $order->tracking_url,
            reason: $order->cancel_reason,
        );

        DB::afterCommit(fn () => Mail::to($order->contact_email)->locale($locale)->queue($mail));
    }

    /**
     * Tells the platform team; no address or contact data in the mail.
     */
    private function mailPlatform(ShopOrder $order): void
    {
        $recipient = ShopSetting::current()->notification_email ?? SiteSetting::existing()?->contact_email;

        if ($recipient === null || $recipient === '') {
            return;
        }

        $mail = new NewShopOrderMail((string) $order->order_number, $this->businessName($this->context->tenant()), $order->total_amount, $order->currency, url('/admin/shop-orders'));

        DB::afterCommit(fn () => Mail::to($recipient)->locale(config('locales.default'))->queue($mail));
    }

    private function businessName(Tenant $tenant): string
    {
        return $tenant->translate($tenant->default_locale)?->name ?? $tenant->slug;
    }

    private function ensureCurrentTenant(ShopOrder $order): void
    {
        if (! $this->context->check() || $this->context->id() !== (int) $order->tenant_id) {
            throw new CrossTenantWriteException('A shop order can only be changed in its own TenantContext.');
        }
    }
}
