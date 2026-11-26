<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Support\Money;
use DateTimeInterface;

/**
 * Everything the provider needs to start one checkout. Built by
 * CheckoutService or ShopCheckoutService from the payment row and the billing profile snapshot.
 */
final readonly class CheckoutRequest
{
    /**
     * @param  list<CheckoutLine>  $lines  sum equals $amount
     * @param  array<string, string|null>  $billing  TenantBillingProfile::snapshot()
     * @param  array<string, string|null>|null  $shipping  delivery address; required when a line is physical
     */
    public function __construct(
        public string $reference,
        public Money $amount,
        public array $lines,
        public string $buyerId,
        public string $buyerName,
        public string $buyerEmail,
        public array $billing,
        public string $buyerIp,
        public string $callbackUrl,
        public string $locale,
        public ?DateTimeInterface $buyerRegisteredAt = null,
        public ?array $shipping = null,
    ) {}
}
