<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Queued, Restano-branded email verification mail (tr/en). The locale is
 * fixed when the notification is queued (User::sendEmailVerificationNotification),
 * the signed link is built by the worker from APP_URL.
 */
class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function toMail($notifiable): MailMessage
    {
        $subject = __('auth.mail.verify.subject');

        return (new MailMessage)
            ->subject($subject)
            ->view(['mail.auth-action', 'mail.auth-action-text'], [
                'subject' => $subject,
                'name' => $notifiable->name,
                'intro' => __('auth.mail.verify.intro'),
                'cta' => __('auth.mail.verify.cta'),
                'url' => $this->verificationUrl($notifiable),
                'expiry' => __('auth.mail.verify.expiry', ['count' => config('auth.verification.expire', 60)]),
                'outro' => __('auth.mail.verify.outro'),
            ]);
    }
}
