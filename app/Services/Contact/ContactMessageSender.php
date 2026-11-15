<?php

declare(strict_types=1);

namespace App\Services\Contact;

use App\Mail\ContactMessageMail;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Mail;

/**
 * Queues a contact form message to the platform inbox: the contact email from
 * the /admin site settings, or the mail sender address while none is set.
 * Messages are not stored.
 */
final class ContactMessageSender
{
    public function send(ContactMessage $message): void
    {
        Mail::to($this->recipient())
            ->locale(config('locales.default'))
            ->queue(new ContactMessageMail($message));
    }

    private function recipient(): string
    {
        return SiteSetting::existing()?->contact_email ?? (string) config('mail.from.address');
    }
}
