<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\TenantMembership;
use App\Models\User;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant for /account (tenancy.md § Tenant identification strategy).
 *
 * The tenant comes only from the signed-in user's memberships, never from the
 * request: the first active membership, members of active tenants first, the
 * oldest membership first (until a selector exists). TenantContext is set only
 * after membershipFor() confirms it grants access.
 *
 * - no membership, platform role → redirect to /admin
 * - no membership at all → 403 page ("no business linked")
 * - only memberships of suspended tenants → 403 page with an explanation;
 *   the context is never set and no tenant data is read
 */
class ResolveAccountTenant
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->context->clear();

        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $membership = $this->selectMembership($user);

        if ($membership === null) {
            if ($user->hasPlatformRole()) {
                return redirect('/admin');
            }

            return $this->unavailable('no_business');
        }

        if ($user->membershipFor($membership->tenant) === null) {
            return $this->unavailable('suspended');
        }

        $this->context->set($membership->tenant);

        return $next($request);
    }

    private function selectMembership(User $user): ?TenantMembership
    {
        return $user->memberships()
            ->active()
            ->with('tenant')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (TenantMembership $membership): int => $membership->tenant->isActive() ? 0 : 1)
            ->first();
    }

    private function unavailable(string $reason): Response
    {
        return response()
            ->view('account.unavailable', ['reason' => $reason], 403)
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex');
    }
}
