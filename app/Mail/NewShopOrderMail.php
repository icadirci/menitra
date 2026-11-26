<?php

declare(strict_types=1);

namespace App\Mail;

use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * New paid shop order, to the platform team. No personal data.
 */
class NewShopOrderMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $orderNumber,
        public readonly string $businessName,
        public readonly int $totalMinor,
        public readonly string $currency,
        public readonly string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('shop.mail.admin.subject', ['number' => $this->orderNumber]));
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.new-shop-order-text',
            with: ['total' => Money::fromMinor($this->totalMinor, $this->currency)->format()],
        );
    }
}
