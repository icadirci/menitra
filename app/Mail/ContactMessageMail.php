<?php

declare(strict_types=1);

namespace App\Mail;

use App\Services\Contact\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Contact form message for the platform team. Queued (platform job: no tenant
 * context). Reply-To is the visitor so the team can answer directly. The
 * visitor's text is rendered escaped, never as Markdown or HTML.
 */
class ContactMessageMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly ContactMessage $contact) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->contact->email, $this->contact->name)],
            subject: __('marketing.contact_mail.subject', [
                'subject' => $this->contact->subject->label(),
                'name' => $this->contact->name,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.contact-message',
            text: 'mail.contact-message-text',
        );
    }
}
