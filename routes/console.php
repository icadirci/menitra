<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Subscription lifecycle: trial/period end → grace → expired (docs/architecture/subscriptions.md).
Schedule::command('subscriptions:process-lifecycle')->hourly()->withoutOverlapping()->onOneServer();

// Payments (docs/architecture/subscriptions.md § Payments): settle open checkouts
// the browser never returned from, and remind owners before trial/period end.
Schedule::command('billing:reconcile-payments')->everyTenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('billing:send-reminders')->dailyAt('09:00')->timezone('Europe/Istanbul')->withoutOverlapping()->onOneServer();

// Failed jobs, disk usage, backup age → mail to the platform team (config/ops.php).
Schedule::command('ops:health-check')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
