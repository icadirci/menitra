<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\AdminAuditAction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Limited impersonation (launch plan Faz 8, security.md § Impersonation).
 *
 * - Only a super admin, only into a user without a platform role (UserPolicy),
 *   never nested.
 * - The state lives only in the server-side session: impersonator id, the
 *   impersonated user id and the start time. It ends automatically after
 *   MAX_MINUTES (EnforceImpersonation).
 * - The impersonated user goes through the normal /app membership checks;
 *   nothing grants extra permission inside a tenant.
 * - Start and end are audited; both regenerate the session id.
 */
final class Impersonation
{
    public const string SESSION_KEY = 'impersonation';

    public const int MAX_MINUTES = 60;

    public function __construct(private readonly AdminAudit $audit) {}

    public function isActive(): bool
    {
        return $this->state() !== null;
    }

    /**
     * @return array{impersonator_id: int, user_id: int, started_at: int}|null
     */
    public function state(): ?array
    {
        // Requests without the session middleware (public menus), commands and
        // jobs have no started session, so there is no state.
        $session = session()->driver();

        if (! $session->isStarted()) {
            return null;
        }

        $state = $session->get(self::SESSION_KEY);

        return is_array($state) && isset($state['impersonator_id'], $state['user_id'], $state['started_at']) ? $state : null;
    }

    public function expiresAt(): ?CarbonImmutable
    {
        $state = $this->state();

        return $state === null ? null : CarbonImmutable::createFromTimestamp($state['started_at'])->addMinutes(self::MAX_MINUTES);
    }

    public function hasExpired(): bool
    {
        $expiresAt = $this->expiresAt();

        return $expiresAt !== null && $expiresAt->isPast();
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function start(User $admin, User $target): void
    {
        if ($this->isActive()) {
            throw new AuthorizationException;
        }

        Gate::forUser($admin)->authorize('impersonate', $target);

        if (! $target->canAccessPanel(Filament::getPanel('app'))) {
            throw ValidationException::withMessages(['user' => __('admin.impersonation.errors.no_membership')]);
        }

        DB::transaction(fn () => $this->audit->record(AdminAuditAction::ImpersonationStarted, $admin, $target));

        // login() migrates the session id and stores the target's password hash
        // for AuthenticateSession; the state is written into the new session.
        Auth::guard('web')->login($target);
        session()->regenerate();
        session()->put(self::SESSION_KEY, [
            'impersonator_id' => (int) $admin->getKey(),
            'user_id' => (int) $target->getKey(),
            'started_at' => now()->getTimestamp(),
        ]);
    }

    /**
     * Ends the impersonation and restores the admin, or signs out completely
     * when the impersonator is no longer an active super admin.
     *
     * @param  'left'|'expired'|'logout'  $reason
     */
    public function leave(string $reason = 'left'): ?User
    {
        $state = $this->state();

        if ($state === null) {
            return null;
        }

        $session = session()->driver();
        $session->forget(self::SESSION_KEY);

        $admin = User::query()->find($state['impersonator_id']);
        $target = User::query()->find($state['user_id']);

        if ($admin !== null) {
            $this->audit->record(AdminAuditAction::ImpersonationEnded, $admin, $target, [
                'reason' => $reason,
                'minutes' => (int) round((now()->getTimestamp() - $state['started_at']) / 60),
            ]);
        }

        if ($reason === 'logout') {
            return null;
        }

        if ($admin === null || ! $admin->isSuperAdmin() || $admin->isDeactivated()) {
            Auth::guard('web')->logout();
            $session->invalidate();
            $session->regenerateToken();

            return null;
        }

        Auth::guard('web')->login($admin);
        $session->regenerate();
        $session->regenerateToken();

        return $admin;
    }
}
