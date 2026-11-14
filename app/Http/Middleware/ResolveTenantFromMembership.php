<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Business panel tenant middleware (Livewire-persistent).
 *
 * Runs after Filament's IdentifyTenant has loaded the tenant from the URL slug
 * and called canAccessTenant(). It re-verifies the user's active membership
 * itself and only then fills TenantContext, so tenant-owned queries are scoped
 * by BelongsToTenant independently of Filament's own tenant handling.
 *
 * - not a member (or inactive membership) → 404, existence not revealed
 * - member of a suspended tenant → 403 with an explanation; data stays intact
 *   and the context is never set
 */
class ResolveTenantFromMembership
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();
        $user = $request->user();

        if (! $tenant instanceof Tenant || ! $user instanceof User || ! $user->isMemberOf($tenant)) {
            $this->context->clear();

            abort(404);
        }

        if ($tenant->status === TenantStatus::Suspended) {
            $this->context->clear();

            abort(403, __('tenancy.suspended'));
        }

        if ($user->membershipFor($tenant) === null) {
            $this->context->clear();

            abort(404);
        }

        $this->context->set($tenant);

        return $next($request);
    }
}
