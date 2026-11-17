<?php

declare(strict_types=1);

use App\Http\Controllers\Billing\IyzicoCallbackController;
use App\Http\Controllers\Billing\IyzicoWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Payment provider endpoints (central domain only)
|--------------------------------------------------------------------------
|
| Registered without the `web` group (bootstrap/app.php): no session, no
| cookies, no CSRF. The provider's browser callback is a cross-site POST that
| carries no session cookie; starting a session would replace the customer's
| cookie and sign them out. Nothing posted is trusted (see the controllers).
|
*/

Route::domain(config('tenancy.central_domain'))
    ->middleware('throttle:billing')
    ->group(function (): void {
        Route::post('/billing/iyzico/callback', IyzicoCallbackController::class)->name('billing.iyzico.callback');
        Route::post('/billing/iyzico/webhook', IyzicoWebhookController::class)->name('billing.iyzico.webhook');
    });
