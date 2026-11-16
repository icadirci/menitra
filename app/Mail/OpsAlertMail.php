<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Operational alert for the platform team (ops:health-check). ShouldQueue
 * like every mailable, but the command sends it with sendNow(): the queue
 * may be what is broken.
 */
class OpsAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $problems
     */
    public function __construct(public readonly array $problems) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('ops.mail.subject', [
                'app' => config('app.name'),
                'env' => app()->environment(),
                'count' => count($this->problems),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.ops-alert',
            text: 'mail.ops-alert-text',
        );
    }
}
