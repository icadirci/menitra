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
 * An owner reported a bank transfer, to the platform team. No personal data.
 */
class BankTransferNotifiedMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $reference,
        public readonly string $businessName,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('billing.bank_transfer.mail.subject', ['reference' => $this->reference]));
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.bank-transfer-notified-text',
            with: ['amount' => Money::fromMinor($this->amountMinor, $this->currency)->format()],
        );
    }
}
