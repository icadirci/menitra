<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\AdminAuditAction;
use App\Enums\PlatformRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * User management actions of /admin (launch plan Faz 8), authorized against
 * UserPolicy for the acting user and audited. `platform_role` and
 * `deactivated_at` are set only here (and by artisan commands), never from
 * mass assignment. Users are never deleted.
 */
final class UserAdminService
{
    public function __construct(private readonly AdminAudit $audit) {}

    /**
     * Creates a platform user with an unusable random password and mails a
     * password reset link (queued); two-factor is set up at the first /admin
     * sign-in.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function createPlatformUser(User $actor, string $name, string $email, PlatformRole|string $role): User
    {
        Gate::forUser($actor)->authorize('create', User::class);

        $data = Validator::make(['name' => trim($name), 'email' => Str::lower(trim($email)), 'role' => $role instanceof PlatformRole ? $role->value : $role], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(PlatformRole::class)],
        ], [], [
            'name' => __('admin.users.fields.name'),
            'email' => __('admin.users.fields.email'),
            'role' => __('admin.users.fields.platform_role'),
        ])->validate();

        $user = DB::transaction(function () use ($actor, $data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make(Str::random(64)),
            ]);
            $user->forceFill(['platform_role' => PlatformRole::from($data['role'])])->save();

            $this->audit->record(AdminAuditAction::PlatformUserCreated, $actor, $user, ['role' => $data['role']]);

            return $user;
        });

        Password::broker()->sendResetLink(['email' => $user->email]);

        return $user;
    }

    /**
     * Grants, changes or removes a platform role (null = none).
     *
     * @throws AuthorizationException
     */
    public function changePlatformRole(User $actor, User $user, PlatformRole|string|null $role): void
    {
        Gate::forUser($actor)->authorize('changePlatformRole', $user);

        $role = is_string($role) ? (PlatformRole::tryFrom($role) ?? throw ValidationException::withMessages(['role' => __('validation.enum', ['attribute' => __('admin.users.fields.platform_role')])])) : $role;
        $from = $user->platform_role;

        if ($from === $role) {
            return;
        }

        DB::transaction(function () use ($actor, $user, $role, $from): void {
            $user->forceFill(['platform_role' => $role])->save();
            $this->audit->record(AdminAuditAction::PlatformRoleChanged, $actor, $user, [
                'from' => $from?->value,
                'to' => $role?->value,
            ]);
        });
    }

    /**
     * At most once per minute per user.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function resendVerification(User $actor, User $user): void
    {
        Gate::forUser($actor)->authorize('resendVerification', $user);

        $key = 'admin-resend-verification:'.$user->getKey();

        if (RateLimiter::tooManyAttempts($key, 1)) {
            throw ValidationException::withMessages(['user' => __('admin.users.errors.resend_throttled')]);
        }

        RateLimiter::hit($key, 60);

        $user->sendEmailVerificationNotification();
        $this->audit->record(AdminAuditAction::VerificationResent, $actor, $user);
    }

    /**
     * @throws AuthorizationException
     */
    public function deactivate(User $actor, User $user): void
    {
        Gate::forUser($actor)->authorize('deactivate', $user);

        DB::transaction(function () use ($actor, $user): void {
            $user->forceFill(['deactivated_at' => now()])->save();
            $this->audit->record(AdminAuditAction::UserDeactivated, $actor, $user);
        });
    }

    /**
     * @throws AuthorizationException
     */
    public function reactivate(User $actor, User $user): void
    {
        Gate::forUser($actor)->authorize('reactivate', $user);

        DB::transaction(function () use ($actor, $user): void {
            $user->forceFill(['deactivated_at' => null])->save();
            $this->audit->record(AdminAuditAction::UserReactivated, $actor, $user);
        });
    }
}
