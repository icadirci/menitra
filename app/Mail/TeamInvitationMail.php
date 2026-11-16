<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\TenantRole;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Team invitation (TeamInvitationService). Queued after commit, in the
 * invitation's locale. The business and inviter names are rendered escaped
 * (HTML view + plain text, no Markdown). The URL carries the raw token, which
 * exists nowhere else.
 */
class TeamInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $url,
        public readonly string $businessName,
        public readonly string $inviterName,
        public readonly TenantRole $role,
        public readonly CarbonImmutable $expiresAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('team.mail.subject', ['business' => $this->businessName]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.team-invitation',
            text: 'mail.team-invitation-text',
            with: [
                'roleLabel' => $this->role->label(),
                'days' => (int) config('tenancy.team.invitation_days'),
            ],
        );
    }
}
