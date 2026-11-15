<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TenantRegistered;
use App\Mail\WelcomeMail;
use App\Services\Registration\WelcomeMessage;
use Illuminate\Support\Facades\Mail;

/**
 * Queues the welcome mail of a new business in its registration language.
 * A platform mail: it receives plain data only (WelcomeMessage), never a
 * model or a tenant id, so the queued job runs without a tenant context.
 */
final class SendWelcomeMail
{
    public function handle(TenantRegistered $event): void
    {
        $message = new WelcomeMessage(
            name: $event->user->name,
            businessName: $event->businessName,
            panelUrl: route('filament.app.pages.dashboard', ['tenant' => $event->tenant->slug]),
            trialEndsOn: $event->trialEndsAt?->locale($event->locale)->isoFormat('LL'),
            locale: $event->locale,
        );

        Mail::to($event->user->email, $event->user->name)
            ->locale($event->locale)
            ->queue(new WelcomeMail($message));
    }
}
