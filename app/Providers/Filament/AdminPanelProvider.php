<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Shared\Auth\EditProfile;
use App\Filament\Shared\Auth\MultiFactor;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Platform administration panel (/admin) for users with a platform role.
 * Access is decided by User::canAccessPanel(). Registration and password
 * reset are intentionally disabled (see docs/architecture/overview.md).
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->domain(config('tenancy.central_domain'))
            ->login()
            // Two-factor is mandatory: a platform user without it is sent to the
            // set-up page and reaches nothing else. Same providers as /app
            // (MultiFactor), so neither login skips the challenge.
            ->multiFactorAuthentication(MultiFactor::providers(), isRequired: true)
            ->profile(EditProfile::class, isSimple: false)
            ->brandName(fn (): string => __('panels.admin.brand'))
            ->brandLogo(fn () => view('filament.brand-logo', ['name' => __('panels.admin.brand')]))
            ->brandLogoHeight('2rem')
            ->favicon(fn (): string => asset('favicon.svg'))
            ->colors([
                // Restano design system: Forest primary on warm neutrals.
                'primary' => Color::hex('#1F4D3B'),
                'gray' => Color::Stone,
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
