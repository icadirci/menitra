<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central (SaaS marketing) routes
|--------------------------------------------------------------------------
|
| Registered for the central domain only. Tenant subdomains have no route
| here, so e.g. {slug}.restano.app/ returns 404 until public menus exist.
|
*/

Route::domain(config('tenancy.central_domain'))->group(function (): void {
    Route::get('/', function () {
        return view('welcome');
    });
});

require __DIR__.'/auth.php';
