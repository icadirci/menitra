<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\App\Navigation\AppNavigationGroup;
use App\Filament\App\Pages\Dashboard;
use App\Filament\Shared\Auth\EditProfile;
use App\Filament\Shared\Auth\MultiFactor;
use App\Http\Middleware\EnforceImpersonation;
use App\Http\Middleware\LogoutDeactivatedUsers;
use App\Http\Middleware\ResolveTenantFromMembership;
use App\Models\Tenant;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Business management panel (/app/{tenant-slug}) for tenant members.
 *
 * - Panel access: User::canAccessPanel() (active membership required) and a
 *   verified email address (EnsureEmailIsVerified).
 * - Tenant access: Filament resolves the slug and calls User::canAccessTenant();
 *   ResolveTenantFromMembership re-verifies and fills TenantContext.
 * - Isolation: BelongsToTenant on every tenant-owned model, not Filament's
 *   ownership scoping (see docs/architecture/tenancy.md).
 *
 * Registration and password reset go through Fortify, and tenants are created
 * only by TenantRegistrationService, so panel and tenant registration are off.
 */
class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('app')
            ->domain(config('tenancy.central_domain'))
            ->login()
            // Two-factor is optional here, managed on the profile page. The
            // providers must match /admin (see MultiFactor).
            ->multiFactorAuthentication(MultiFactor::providers())
            ->profile(EditProfile::class, isSimple: false)
            // Saves and actions run in a DB transaction, so uploads stored during
            // a failed save are removed (MediaCleaner) and writes stay atomic.
            ->databaseTransactions()
            ->tenant(Tenant::class, slugAttribute: 'slug')
            ->tenantMiddleware([
                ResolveTenantFromMembership::class,
            ], isPersistent: true)
            ->brandName(fn (): string => __('panels.app.brand'))
            ->brandLogo(fn () => view('filament.brand-logo', ['name' => __('panels.app.brand')]))
            ->brandLogoHeight('2rem')
            ->favicon(fn (): string => asset('favicon.svg'))
            ->colors([
                // Menitra design system: Forest primary on warm neutrals.
                'primary' => Color::hex('#1F4D3B'),
                'gray' => Color::Stone,
            ])
            ->navigationGroups(AppNavigationGroup::class)
            // "Viewing as X / leave" band while a super admin impersonates.
            ->renderHook(PanelsRenderHook::BODY_START, fn (): string => view('filament.app.impersonation-banner')->render())
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\Filament\App\Resources')
            ->discoverPages(in: app_path('Filament/App/Pages'), for: 'App\Filament\App\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/App/Widgets'), for: 'App\Filament\App\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                EnforceImpersonation::class,
                LogoutDeactivatedUsers::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            // A verified email is required for the business panel; unverified users
            // are sent to Fortify's notice page (/email/verify). Persistent, so it
            // is enforced on Livewire updates too (403 for JSON requests).
            ->authMiddleware([
                Authenticate::class,
                EnsureEmailIsVerified::class,
            ], isPersistent: true);
    }
}
