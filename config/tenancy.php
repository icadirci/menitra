<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Central domain
    |--------------------------------------------------------------------------
    |
    | The platform's own host. Marketing, auth, both Filament panels (/admin,
    | /app) and Livewire's endpoints are registered for this host only; tenant
    | subdomains ({slug}.central_domain) never serve them.
    |
    */

    'central_domain' => env('CENTRAL_DOMAIN', 'restano.app'),

    /*
    |--------------------------------------------------------------------------
    | Registration defaults
    |--------------------------------------------------------------------------
    |
    | Slugs of the location and menu created by TenantRegistrationService.
    |
    */

    'default_location_slug' => 'main',

    'default_menu_slug' => 'menu',

    /*
    |--------------------------------------------------------------------------
    | Reserved slugs
    |--------------------------------------------------------------------------
    |
    | Tenant slugs that would collide with platform routes or subdomains.
    | See docs/architecture/tenancy.md.
    |
    */

    'reserved_slugs' => [
        'www', 'app', 'api', 'admin', 'dashboard', 'account', 'q', 'm', 'mail', 'smtp',
        'static', 'cdn', 'assets', 'img', 'docs', 'help', 'support', 'status', 'blog',
        'billing', 'pricing', 'fiyatlar', 'features', 'ozellikler', 'contact', 'iletisim',
        'login', 'register',
    ],

];
