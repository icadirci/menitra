<?php

declare(strict_types=1);

use Laravel\Fortify\Features;

/*
|--------------------------------------------------------------------------
| Fortify (headless)
|--------------------------------------------------------------------------
|
| Fortify handles public registration, password reset and email
| verification only. Login is Filament's (/admin/login, /app/login), so
| Fortify's own routes are ignored (FortifyServiceProvider) and the routes
| we need are registered in routes/auth.php for the central domain.
|
*/

return [

    'guard' => 'web',

    'passwords' => 'users',

    'username' => 'email',

    'email' => 'email',

    'lowercase_usernames' => true,

    // After registration and email verification: the business panel, which
    // redirects to the user's tenant (/app/{tenant-slug}).
    'home' => '/app',

    'prefix' => '',

    'domain' => null,

    'middleware' => ['web'],

    'limiters' => [
        'login' => null,
        'verification' => '6,1',
    ],

    'views' => true,

    'features' => [
        Features::registration(),
        Features::resetPasswords(),
        Features::emailVerification(),
    ],

];
