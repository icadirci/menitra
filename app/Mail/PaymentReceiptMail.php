<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\BillingInterval;
use App\Mail\Concerns\SentFromBillingAddress;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Payment receipt to the billing email (PaymentProcessor, queued after
 * commit, in the business' default locale). Plain values only; the business
 * name is tenant input and rendered escaped (HTML view + plain text). Not an
 * invoice: the official e-Arşiv invoice is issued separately.
 */
class PaymentReceiptMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SentFromBillingAddress;

    public int $tries = 3;

    public function __construct(
        public readonly string $receiptNumber,
        public readonly string $businessName,
        public readonly string $planName,
        public readonly BillingInterval $interval,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly CarbonImmutable $periodStartsAt,
        public readonly CarbonImmutable $periodEndsAt,
        public readonly CarbonImmutable $paidAt,
        public readonly string $url,
        public readonly int $periodCount = 1,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->billingFrom(),
            subject: __('billing.mail.receipt.subject', ['number' => $this->receiptNumber]),
        );
    }

    public function content(): Content
    {
        $date = fn (CarbonImmutable $value): string => $value->setTimezone('Europe/Istanbul')->locale(app()->getLocale())->isoFormat('LL');

        return new Content(
            view: 'mail.payment-receipt',
            text: 'mail.payment-receipt-text',
            with: [
                'amount' => Money::fromMinor($this->amountMinor, $this->currency)->format(),
                'intervalLabel' => $this->interval->periodLabel($this->periodCount),
                'period' => $date($this->periodStartsAt).' – '.$date($this->periodEndsAt),
                'paidOn' => $date($this->paidAt),
            ],
        );
    }
}
