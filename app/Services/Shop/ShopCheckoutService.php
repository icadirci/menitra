<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ShopOrderStatus;
use App\Exceptions\CheckoutNotAllowedException;
use App\Exceptions\CrossTenantWriteException;
use App\Exceptions\PaymentGatewayException;
use App\Filament\App\Pages\BankTransferPayment;
use App\Models\Payment;
use App\Models\ShopCartItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\Tenant;
use App\Models\TenantBillingProfile;
use App\Models\User;
use App\Services\Billing\BankTransferService;
use App\Services\Billing\BillingProfileService;
use App\Services\Billing\PaymentMethods;
use App\Support\Billing\CheckoutLine;
use App\Support\Billing\CheckoutRequest;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Turns the cart into an order (awaiting_payment) with one pending payment
 * and opens the provider's hosted checkout (docs/architecture/shop.md). The
 * cart is kept until the payment succeeds, so a failed payment can simply be
 * tried again. Amounts come only from the products and the shop settings.
 */
class ShopCheckoutService
{
    /** Pending checkouts (plans included) a tenant may open per hour. */
    private const int MAX_ATTEMPTS_PER_HOUR = 10;

    public function __construct(
        private readonly TenantContext $context,
        private readonly PaymentGateway $gateway,
        private readonly ShopCatalog $catalog,
        private readonly ShopCart $cart,
        private readonly DeliveryAddress $addresses,
        private readonly BillingProfileService $profiles,
        private readonly PaymentMethods $methods,
        private readonly BankTransferService $bankTransfers,
    ) {}

    /**
     * @param  array<string, mixed>  $address
     * @param  int  $expectedTotal  the total the owner saw; a different one now is refused
     * @return string the provider's payment page URL, or the bank transfer page
     *
     * @throws AuthorizationException|CheckoutNotAllowedException|ValidationException
     */
    public function start(Tenant $tenant, User $user, array $address, ?string $note, int $expectedTotal, string $ip, string $locale, PaymentMethod $method = PaymentMethod::Card): string
    {
        if (! $this->context->check() || $this->context->id() !== (int) $tenant->getKey()) {
            throw new CrossTenantWriteException('A shop order can only be placed for the tenant in the current TenantContext.');
        }

        if (! $user->can('manageSettings', $tenant)) {
            throw new AuthorizationException;
        }

        if (! $this->catalog->isOpen()) {
            throw CheckoutNotAllowedException::because('shop_closed');
        }

        $this->methods->ensureEnabled($method);
        // An order waiting for its transfer would be paid twice with the same cart.
        $this->bankTransfers->ensureNoneOpen(shop: true);

        $delivery = $this->addresses->validate($address);
        $note = trim((string) $note);

        if (mb_strlen($note) > 500) {
            throw ValidationException::withMessages(['note' => __('validation.max.string', ['attribute' => __('shop.checkout.note'), 'max' => 500])]);
        }

        $profile = $this->profiles->current() ?? throw CheckoutNotAllowedException::because('billing_profile_missing');
        $quote = $this->cart->quote();
        $this->ensureBuyable($quote);

        if ($quote->total->minor !== $expectedTotal) {
            throw CheckoutNotAllowedException::because('shop_price_changed');
        }

        if (Payment::query()->where('created_at', '>=', now()->subHour())->count() >= self::MAX_ATTEMPTS_PER_HOUR) {
            throw CheckoutNotAllowedException::because('too_many_attempts');
        }

        $payment = DB::transaction(fn (): Payment => $this->createOrder($tenant, $user, $quote, $delivery, $note === '' ? null : $note, $profile, $method));

        // The owner transfers the amount; a super admin marks the order paid once the money arrived.
        if ($payment->isBankTransfer()) {
            return BankTransferPayment::getUrl(['payment' => $payment->public_id], panel: 'app', tenant: $tenant);
        }

        // The request is built from the stored order, never from the cart again.
        $order = ShopOrder::query()->with('items')->findOrFail($payment->shop_order_id);

        try {
            $session = $this->gateway->initializeCheckout($this->request($payment, $order, $user, $ip, $locale));
        } catch (PaymentGatewayException $exception) {
            DB::transaction(function () use ($payment, $order, $exception): void {
                $payment->forceFill([
                    'status' => PaymentStatus::Failed,
                    'failure_code' => $exception->providerCode !== null ? mb_substr('initialize:'.$exception->providerCode, 0, 64) : 'initialize_failed',
                    'failed_at' => CarbonImmutable::now(),
                ])->save();
                $order->forceFill(['status' => ShopOrderStatus::PaymentFailed])->save();
            });

            Log::warning('Shop checkout could not be started.', ['payment' => $payment->public_id, 'error' => $exception->getMessage()]);
            report($exception);

            throw CheckoutNotAllowedException::because($exception->providerCode !== null ? 'gateway_rejected' : 'gateway_unavailable');
        }

        $payment->forceFill(['provider_token' => $session->token])->save();

        return $session->paymentPageUrl;
    }

    private function ensureBuyable(ShopQuote $quote): void
    {
        if ($quote->isEmpty()) {
            throw CheckoutNotAllowedException::because('shop_cart_empty');
        }

        foreach ($quote->items->groupBy('shop_product_id') as $lines) {
            $product = $lines->first()->product;

            if ((int) $lines->sum('quantity') > $product->availableQuantity()) {
                throw CheckoutNotAllowedException::because('shop_stock', [
                    'product' => $product->translate()?->name ?? $product->public_id,
                    'max' => $product->availableQuantity(),
                ]);
            }
        }
    }

    /**
     * @param  array<string, string|null>  $delivery
     */
    private function createOrder(Tenant $tenant, User $user, ShopQuote $quote, array $delivery, ?string $note, TenantBillingProfile $profile, PaymentMethod $method): Payment
    {
        $order = new ShopOrder;
        $order->forceFill([
            'user_id' => $user->getKey(),
            'status' => ShopOrderStatus::AwaitingPayment,
            'currency' => $quote->total->currency,
            'subtotal_amount' => $quote->subtotal->minor,
            'shipping_amount' => $quote->shipping->minor,
            'total_amount' => $quote->total->minor,
            'contact_email' => $profile->email,
            'delivery_address' => $delivery,
            'customer_note' => $note,
        ])->save();

        $order->forceFill(['order_number' => sprintf('MS-%s-%06d', now()->format('Y'), $order->getKey())])->save();

        foreach ($quote->items as $line) {
            /** @var ShopCartItem $line */
            (new ShopOrderItem)->forceFill([
                'shop_order_id' => $order->getKey(),
                'shop_product_id' => $line->shop_product_id,
                'qr_code_id' => $line->qr_code_id,
                'product_name' => $line->product->translate($tenant->default_locale)?->name ?? $line->product->public_id,
                'qr_code' => $line->qrCode?->code,
                'qr_label' => $line->qrCode?->label,
                'unit_amount' => $line->product->price_amount,
                'quantity' => $line->quantity,
            ])->save();
        }

        $payment = new Payment;
        $payment->forceFill([
            'shop_order_id' => $order->getKey(),
            'user_id' => $user->getKey(),
            'kind' => PaymentKind::ShopOrder,
            'status' => PaymentStatus::Pending,
            'currency' => $order->currency,
            'base_amount' => $order->subtotal_amount,
            'amount' => $order->total_amount,
            'provider' => $method === PaymentMethod::BankTransfer ? PaymentMethod::BANK_TRANSFER_PROVIDER : $this->gateway->name(),
            'transfer_reference' => $method === PaymentMethod::BankTransfer ? $this->bankTransfers->newReference() : null,
            'billing_details' => $profile->snapshot(),
        ])->save();

        return $payment;
    }

    private function request(Payment $payment, ShopOrder $order, User $user, string $ip, string $locale): CheckoutRequest
    {
        // One basket line per product, whatever the number of codes.
        $lines = $order->items->groupBy('shop_product_id')->map(function ($items) use ($order): CheckoutLine {
            return new CheckoutLine(
                'product-'.$items->first()->shop_product_id,
                $items->sum('quantity').' × '.$items->first()->product_name,
                Money::fromMinor((int) $items->sum(fn (ShopOrderItem $item): int => $item->unit_amount * $item->quantity), $order->currency),
                physical: true,
            );
        })->values()->all();

        if (! $order->shipping()->isZero()) {
            $lines[] = new CheckoutLine('shipping', __('shop.checkout.shipping', [], $locale), $order->shipping(), physical: true);
        }

        return new CheckoutRequest(
            reference: $payment->public_id,
            amount: $payment->money(),
            lines: $lines,
            buyerId: 'user-'.$user->getKey(),
            buyerName: $user->name,
            buyerEmail: $order->contact_email,
            billing: $payment->billing_details,
            buyerIp: $ip,
            callbackUrl: route('billing.iyzico.callback'),
            locale: $locale,
            buyerRegisteredAt: $user->created_at,
            shipping: $order->delivery_address,
        );
    }
}
