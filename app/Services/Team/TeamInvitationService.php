<?php

declare(strict_types=1);

namespace App\Services\Team;

use App\Enums\PlanFeature;
use App\Enums\TenantRole;
use App\Exceptions\CrossTenantWriteException;
use App\Exceptions\InvitationEmailMismatchException;
use App\Exceptions\PlanLimitExceededException;
use App\Mail\TeamInvitationMail;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Subscription\FeatureGate;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Team invitations (launch plan Faz 7, CLAUDE.md §3.16).
 *
 * Owner side (inside the tenant's context): invite, resend, revoke. The
 * caller authorizes (TenantInvitationPolicy); the service checks again that
 * the acting membership is the tenant's owner, resolves branches through the
 * tenant scope and enforces max_members on invitation (pending invitations
 * count, together with active memberships).
 *
 * Invitee side (central domain, no context): accept. The invitation comes
 * from InvitationTokenResolver; the context is set to its tenant only for the
 * acceptance and restored afterwards. The raw token exists only in the mail.
 */
final class TeamInvitationService
{
    private const int RESEND_COOLDOWN_SECONDS = 60;

    public function __construct(
        private readonly TenantContext $context,
        private readonly FeatureGate $gate,
        private readonly BranchSelection $branches,
    ) {}

    /**
     * @param  list<int|string>  $locationIds
     *
     * @throws ValidationException
     * @throws PlanLimitExceededException
     */
    public function invite(TenantMembership $owner, string $email, TenantRole|string|null $role, array $locationIds): TenantInvitation
    {
        $this->assertOwner($owner);

        $email = $this->email($email);
        $role = $this->role($role);
        $locations = $this->branches->resolve($locationIds);

        if ($this->memberWithEmail($email) !== null) {
            throw ValidationException::withMessages(['email' => __('team.errors.already_member')]);
        }

        [$invitation, $token] = DB::transaction(function () use ($owner, $email, $role, $locations): array {
            $this->lockTenant();

            $open = TenantInvitation::query()->open()->where('email', $email)->lockForUpdate()->first();

            if ($open !== null && $open->isPending()) {
                throw ValidationException::withMessages(['email' => __('team.errors.already_invited')]);
            }

            // An expired open invitation gives way to the new one.
            $open?->forceFill(['revoked_at' => now()])->save();

            $this->gate->ensureCanCreate($this->context->tenant(), PlanFeature::MaxMembers);

            $token = $this->newToken();
            $invitation = new TenantInvitation;
            $invitation->forceFill([
                'email' => $email,
                'role' => $role,
                'token_hash' => TenantInvitation::hashToken($token),
                'locale' => $this->locale(),
                'invited_by_membership_id' => $owner->getKey(),
                'expires_at' => $this->expiresAt(),
                'sent_at' => now(),
            ])->save();
            $invitation->locations()->attach($locations->modelKeys());

            return [$invitation, $token];
        });

        $this->send($invitation, $token, $owner);

        return $invitation;
    }

    /**
     * A new token and expiry; the previous link stops working.
     *
     * @throws ValidationException
     * @throws PlanLimitExceededException
     */
    public function resend(TenantMembership $owner, TenantInvitation $invitation): TenantInvitation
    {
        $this->assertOwner($owner);
        $this->assertCurrentTenant($invitation);

        $token = DB::transaction(function () use ($invitation): string {
            $this->lockTenant();
            $invitation = TenantInvitation::query()->lockForUpdate()->findOrFail($invitation->getKey());

            if (! $invitation->isOpen()) {
                throw ValidationException::withMessages(['invitation' => __('team.errors.invitation_closed')]);
            }

            if ($invitation->sent_at->addSeconds(self::RESEND_COOLDOWN_SECONDS)->isFuture()) {
                throw ValidationException::withMessages(['invitation' => __('team.errors.resend_too_soon')]);
            }

            // An expired invitation stopped counting; resending makes it count again.
            if (! $invitation->isPending()) {
                $this->gate->ensureCanCreate($this->context->tenant(), PlanFeature::MaxMembers);
            }

            $token = $this->newToken();
            $invitation->forceFill([
                'token_hash' => TenantInvitation::hashToken($token),
                'expires_at' => $this->expiresAt(),
                'sent_at' => now(),
            ])->save();

            return $token;
        });

        $invitation->refresh();
        $this->send($invitation, $token, $owner);

        return $invitation;
    }

    /**
     * @throws ValidationException
     */
    public function revoke(TenantMembership $owner, TenantInvitation $invitation): void
    {
        $this->assertOwner($owner);
        $this->assertCurrentTenant($invitation);

        if (! $invitation->isOpen()) {
            throw ValidationException::withMessages(['invitation' => __('team.errors.invitation_closed')]);
        }

        $invitation->forceFill(['revoked_at' => now()])->save();
    }

    /**
     * Signed-in user: only with the invited email address.
     *
     * @throws InvitationEmailMismatchException
     * @throws ValidationException
     */
    public function acceptAsUser(TenantInvitation $invitation, User $user): TenantMembership
    {
        if (! $this->sameEmail($user->email, $invitation->email)) {
            throw new InvitationEmailMismatchException;
        }

        return $this->accept($invitation, $user);
    }

    /**
     * Existing account, not signed in: confirms with its current password.
     * No session is started; the user then signs in through the panel login
     * (which asks for the two-factor code when enabled).
     *
     * @throws ValidationException
     */
    public function acceptWithPassword(TenantInvitation $invitation, #[SensitiveParameter] string $password): TenantMembership
    {
        $user = $this->userWithEmail($invitation->email);

        // A deactivated account gets the same answer as a wrong password.
        if ($user === null || ! Hash::check($password, $user->password) || $user->isDeactivated()) {
            throw ValidationException::withMessages(['password' => __('team.accept.errors.password')]);
        }

        return $this->accept($invitation, $user);
    }

    /**
     * New account: the token proved the email address, so it counts as
     * verified. Input is validated by the caller (Fortify password rules).
     *
     * @throws ValidationException
     */
    public function acceptAsNewUser(TenantInvitation $invitation, string $name, #[SensitiveParameter] string $password): TenantMembership
    {
        return DB::transaction(function () use ($invitation, $name, $password): TenantMembership {
            if ($this->userWithEmail($invitation->email) !== null) {
                throw ValidationException::withMessages(['email' => __('team.accept.errors.account_exists')]);
            }

            $user = User::create([
                'name' => $name,
                'email' => $invitation->email,
                'password' => Hash::make($password),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            // The caller validated the terms checkbox (AcceptInvitationRequest).
            $user->recordTermsAcceptance();

            return $this->accept($invitation, $user);
        });
    }

    public function userWithEmail(string $email): ?User
    {
        return User::query()->whereRaw('lower(email) = ?', [Str::lower($email)])->first();
    }

    /**
     * @throws ValidationException
     */
    private function accept(TenantInvitation $invitation, User $user): TenantMembership
    {
        $tenant = Tenant::query()->find($invitation->tenant_id);

        if ($tenant === null || ! $tenant->isActive()) {
            throw ValidationException::withMessages(['invitation' => __('team.accept.invalid.body')]);
        }

        $previous = $this->context->check() ? $this->context->tenant() : null;
        $this->context->set($tenant);

        try {
            return DB::transaction(function () use ($invitation, $user, $tenant): TenantMembership {
                $invitation = TenantInvitation::query()->lockForUpdate()->find($invitation->getKey());

                // Re-checked under the lock: single use, not expired, not revoked.
                if ($invitation === null || ! $invitation->isPending()) {
                    throw ValidationException::withMessages(['invitation' => __('team.accept.invalid.body')]);
                }

                if (TenantMembership::query()->where('tenant_id', $tenant->getKey())->where('user_id', $user->getKey())->exists()) {
                    throw ValidationException::withMessages(['invitation' => __('team.accept.errors.already_member')]);
                }

                $locationIds = $invitation->locations()->pluck('locations.id')->all();

                if ($locationIds === []) {
                    throw ValidationException::withMessages(['invitation' => __('team.accept.errors.no_locations')]);
                }

                $membership = new TenantMembership;
                $membership->forceFill([
                    'tenant_id' => $tenant->getKey(),
                    'user_id' => $user->getKey(),
                    'role' => $invitation->role,
                    'is_active' => true,
                ])->save();
                $membership->locations()->attach($locationIds);

                $invitation->forceFill(['accepted_at' => now()])->save();

                if ($user->email_verified_at === null) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }

                return $membership;
            });
        } finally {
            $previous !== null ? $this->context->set($previous) : $this->context->clear();
        }
    }

    private function send(TenantInvitation $invitation, #[SensitiveParameter] string $token, TenantMembership $owner): void
    {
        $tenant = $this->context->tenant();

        Mail::to($invitation->email)->queue(
            (new TeamInvitationMail(
                url: route('invitations.show', ['token' => $token]),
                businessName: $tenant->translate($invitation->locale)?->name ?? $tenant->slug,
                inviterName: (string) $owner->user?->name,
                role: $invitation->role,
                expiresAt: $invitation->expires_at,
            ))->locale($invitation->locale)->afterCommit(),
        );
    }

    /**
     * @throws ValidationException
     */
    private function email(string $email): string
    {
        $email = Str::lower(trim($email));

        if ($email === '' || mb_strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::withMessages(['email' => __('team.errors.email')]);
        }

        return $email;
    }

    /**
     * Invitations name a manager or branch staff role; owners aren't invited.
     *
     * @throws ValidationException
     */
    private function role(TenantRole|string|null $role): TenantRole
    {
        $role = $role instanceof TenantRole ? $role : TenantRole::tryFrom((string) $role);

        if ($role === null || $role === TenantRole::Owner) {
            throw ValidationException::withMessages(['role' => __('team.errors.role')]);
        }

        return $role;
    }

    private function memberWithEmail(string $email): ?TenantMembership
    {
        return TenantMembership::query()
            ->where('tenant_id', $this->context->id())
            ->whereHas('user', fn ($query) => $query->whereRaw('lower(email) = ?', [$email]))
            ->first();
    }

    private function sameEmail(string $a, string $b): bool
    {
        return hash_equals(Str::lower(trim($b)), Str::lower(trim($a)));
    }

    private function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function expiresAt(): CarbonImmutable
    {
        return now()->toImmutable()->addDays((int) config('tenancy.team.invitation_days'));
    }

    private function locale(): string
    {
        $locale = app()->getLocale();

        return array_key_exists($locale, (array) config('locales.supported')) ? $locale : (string) config('locales.default');
    }

    /**
     * Serialises invitations and membership changes of one tenant, so the
     * max_members count can't be raced.
     */
    private function lockTenant(): void
    {
        DB::table('tenants')->where('id', $this->context->id())->lockForUpdate()->first();
    }

    /**
     * @throws AuthorizationException
     */
    private function assertOwner(TenantMembership $owner): void
    {
        if (! $this->context->check() || (int) $owner->tenant_id !== $this->context->id()) {
            throw new CrossTenantWriteException('The membership belongs to another tenant.');
        }

        if (! $owner->isOwner() || ! $owner->is_active) {
            throw new AuthorizationException;
        }
    }

    private function assertCurrentTenant(TenantInvitation $invitation): void
    {
        if ((int) $invitation->tenant_id !== $this->context->id()) {
            throw new CrossTenantWriteException('The invitation belongs to another tenant.');
        }
    }
}
