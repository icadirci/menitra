<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\App\Pages\Dashboard;
use App\Http\Middleware\ResolveTenantFromMembership;
use App\Models\Tenant;
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
 * Business management panel (/app/{tenant-slug}) for tenant members.
 *
 * - Panel access: User::canAccessPanel() (active membership required).
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
            ->tenant(Tenant::class, slugAttribute: 'slug')
            ->tenantMiddleware([
                ResolveTenantFromMembership::class,
            ], isPersistent: true)
            ->brandName(fn (): string => __('panels.app.brand'))
            ->colors([
                'primary' => Color::Amber,
            ])
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
