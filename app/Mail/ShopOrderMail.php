<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\ShopOrderStatus;
use App\Mail\Concerns\SentFromBillingAddress;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Order update to the billing email: received (paid), shipped or cancelled.
 * Plain values only, rendered escaped; no delivery address.
 */
class ShopOrderMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SentFromBillingAddress;

    public int $tries = 3;

    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        public readonly ShopOrderStatus $status,
        public readonly string $orderNumber,
        public readonly string $businessName,
        public readonly array $lines,
        public readonly int $totalMinor,
        public readonly string $currency,
        public readonly string $url,
        public readonly ?string $carrier = null,
        public readonly ?string $trackingNumber = null,
        public readonly ?string $trackingUrl = null,
        public readonly ?string $reason = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(from: $this->billingFrom(), subject: __("shop.mail.{$this->status->value}.subject", ['number' => $this->orderNumber]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.shop-order',
            text: 'mail.shop-order-text',
            with: [
                'intro' => __("shop.mail.{$this->status->value}.intro", ['business' => $this->businessName, 'number' => $this->orderNumber]),
                'total' => Money::fromMinor($this->totalMinor, $this->currency)->format(),
            ],
        );
    }
}
