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

    'central_domain' => env('CENTRAL_DOMAIN', 'menitra.com'),

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
        'themes', 'temalar', 'privacy', 'gizlilik', 'terms', 'kullanim-kosullari', 'sitemap',
        'login', 'register', 'qr-stand', 'qr-stands', 'shop', 'magaza',
    ],

    /*
    |--------------------------------------------------------------------------
    | Public menu cache
    |--------------------------------------------------------------------------
    |
    | TTLs (seconds) of the public menu cache entries (docs/architecture/caching.md).
    | Snapshots are versioned, so the TTL only limits how long stale entries linger.
    |
    */

    'cache' => [
        'resolution_ttl' => 3600,
        'snapshot_ttl' => 86400,
    ],

    /*
    |--------------------------------------------------------------------------
    | Public menu rate limit
    |--------------------------------------------------------------------------
    |
    | Requests per minute and IP on public menu pages (throttle:menu). Generous
    | on purpose: guests of one venue often share the venue Wi-Fi's IP. QR
    | redirects (/q/*) have their own limit (throttle:qr).
    |
    */

    'menu_rate_limit' => (int) env('MENU_RATE_LIMIT', 300),

    /*
    |--------------------------------------------------------------------------
    | Team invitations
    |--------------------------------------------------------------------------
    |
    | Days an invitation link stays valid (launch plan Faz 7). Resending issues
    | a new link with a new expiry.
    |
    */

    'team' => [
        'invitation_days' => 7,
    ],

];
