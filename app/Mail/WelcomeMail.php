<?php

declare(strict_types=1);

namespace App\Mail;

use App\Services\Registration\WelcomeMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Welcome mail after registration (TenantRegistered → SendWelcomeMail).
 * Queued platform mail in the registration locale; the business name is
 * rendered escaped.
 */
class WelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly WelcomeMessage $welcome) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('registration.welcome_mail.subject', ['business' => $this->welcome->businessName]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.welcome',
            text: 'mail.welcome-text',
        );
    }
}
