<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Queued, Menitra-branded password reset mail (tr/en). The locale is fixed
 * when the notification is queued (User::sendPasswordResetNotification); the
 * link is built by the worker from APP_URL.
 */
class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function toMail($notifiable): MailMessage
    {
        $subject = __('auth.mail.reset.subject');
        $broker = config('auth.defaults.passwords');

        return (new MailMessage)
            ->subject($subject)
            ->view(['mail.auth-action', 'mail.auth-action-text'], [
                'subject' => $subject,
                'name' => $notifiable->name,
                'intro' => __('auth.mail.reset.intro'),
                'cta' => __('auth.mail.reset.cta'),
                'url' => $this->resetUrl($notifiable),
                'expiry' => __('auth.mail.reset.expiry', ['count' => config("auth.passwords.{$broker}.expire")]),
                'outro' => __('auth.mail.reset.outro'),
            ]);
    }
}
