<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlatformRole;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use App\Services\Admin\Impersonation;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use SensitiveParameter;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasTenants, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Per-instance cache of membershipFor() results, keyed by tenant id.
     *
     * @var array<int, TenantMembership|null>
     */
    private array $resolvedMemberships = [];

    /**
     * The attributes that are mass assignable. `platform_role` is deliberately
     * excluded: it is granted only explicitly (forceFill / artisan command).
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'platform_role' => PlatformRole::class,
            'deactivated_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            // Two-factor (Filament app authentication): the TOTP secret and the
            // already-hashed recovery codes are stored encrypted.
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->refuseDuringImpersonation();

        $this->forceFill(['app_authentication_secret' => $secret])->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /**
     * @return ?array<string>
     */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /**
     * @param  ?array<string>  $codes
     */
    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->refuseDuringImpersonation();

        $this->forceFill(['app_authentication_recovery_codes' => $codes])->save();
    }

    public function hasTwoFactorEnabled(): bool
    {
        return filled($this->app_authentication_secret);
    }

    /**
     * Two-factor settings are never changed while a super admin impersonates
     * this session (security.md § Impersonation), whichever code path tries.
     */
    private function refuseDuringImpersonation(): void
    {
        abort_if(app(Impersonation::class)->isActive(), 403);
    }

    /**
     * Deactivated by a super admin (launch plan Faz 8): no panel access, signed
     * out on the next request (LogoutDeactivatedUsers). Memberships are kept.
     */
    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    /**
     * Records acceptance of the current terms of use (launch plan Faz 10).
     * Called only by the services behind forms that validated the terms
     * checkbox; never mass-assigned.
     */
    public function recordTermsAcceptance(): void
    {
        $this->forceFill([
            'terms_accepted_at' => now(),
            'terms_version' => (string) config('marketing.terms_version'),
        ])->save();
    }

    public function hasPlatformRole(): bool
    {
        return $this->platform_role !== null;
    }

    /**
     * Account mails are queued; they keep the language of the request that
     * triggered them (the worker has no request locale of its own).
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify((new VerifyEmailNotification)->locale(app()->getLocale()));
    }

    /**
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify((new ResetPasswordNotification($token))->locale(app()->getLocale()));
    }

    public function isSuperAdmin(): bool
    {
        return $this->platform_role === PlatformRole::SuperAdmin;
    }

    /**
     * Panel access is fail-closed: deactivated users, unknown panels and users
     * without an explicit grant are denied. Hiding navigation is never authorization.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->isDeactivated()) {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => $this->hasPlatformRole(),
            'app' => $this->memberships()->active()->exists(),
            default => false,
        };
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    /**
     * The user's active membership in the given tenant, or null when the
     * membership is missing or inactive, or the tenant is suspended or deleted.
     * This is what grants access to the tenant's data (context, policies).
     */
    public function membershipFor(Tenant $tenant): ?TenantMembership
    {
        if (! $tenant->isActive()) {
            return null;
        }

        return $this->resolvedMemberships[$tenant->getKey()] ??= $this->memberships()
            ->where('tenant_id', $tenant->getKey())
            ->where('is_active', true)
            ->first();
    }

    /**
     * Whether the user has an active membership in the tenant, whatever the
     * tenant's status. Members of a suspended tenant are recognised (so they
     * get an explicit "suspended" response), but are never given its data.
     */
    public function isMemberOf(Tenant $tenant): bool
    {
        return ! $tenant->trashed() && $this->memberships()
            ->where('tenant_id', $tenant->getKey())
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Called by Filament with the tenant loaded from the URL slug. The slug is
     * only an identifier; this is the membership check. Non-members get 404;
     * ResolveTenantFromMembership then refuses suspended tenants with 403.
     */
    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof Tenant && $this->isMemberOf($tenant);
    }

    /**
     * Tenants listed in the panel's tenant switcher.
     *
     * @return Collection<int, Tenant>
     */
    public function getTenants(Panel $panel): Collection
    {
        return Tenant::query()
            ->whereHas('memberships', fn ($membership) => $membership
                ->where('user_id', $this->getKey())
                ->where('is_active', true))
            ->with('translations')
            ->orderBy('slug')
            ->get();
    }
}
