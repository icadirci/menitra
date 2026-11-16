<?php

declare(strict_types=1);

namespace App\Filament\Shared\Auth;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Contracts\MultiFactorAuthenticationProvider;

/**
 * The MFA providers of both panels. They must be identical: the panels share
 * the `web` guard and session, and Filament's login only challenges for the
 * providers of the panel being logged into. If /app had fewer providers, an
 * admin could sign in at /app/login without the second factor and then open
 * /admin. Required in /admin, optional in /app (see the panel providers).
 */
final class MultiFactor
{
    /**
     * @return list<MultiFactorAuthenticationProvider>
     */
    public static function providers(): array
    {
        return [
            AppAuthentication::make()
                // One secret serves both panels, so the authenticator entry
                // carries the product name, not a panel's brand.
                ->brandName('Restano')
                ->recoverable(),
        ];
    }
}
