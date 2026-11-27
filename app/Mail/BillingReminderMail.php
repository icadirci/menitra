<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\BillingReminderType;
use App\Mail\Concerns\SentFromBillingAddress;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * "Time to pay" reminder to the owner (SendTenantBillingReminders), in the
 * business' default locale. Plain values; the business name is escaped.
 */
class BillingReminderMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SentFromBillingAddress;

    public int $tries = 3;

    public function __construct(
        public readonly BillingReminderType $type,
        public readonly string $businessName,
        public readonly string $planName,
        public readonly CarbonImmutable $boundaryAt,
        public readonly int $days,
        public readonly string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->billingFrom(),
            subject: __("billing.mail.reminder.{$this->type->value}.subject", ['business' => $this->businessName]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.billing-reminder',
            text: 'mail.billing-reminder-text',
            with: [
                'key' => "billing.mail.reminder.{$this->type->value}",
                'date' => $this->boundaryAt->setTimezone('Europe/Istanbul')->locale(app()->getLocale())->isoFormat('LL'),
            ],
        );
    }
}
