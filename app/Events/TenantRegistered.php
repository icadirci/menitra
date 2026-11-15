<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A business registered (TenantRegistrationService). Dispatched only after
 * the registration transaction commits, never after a rollback. v1 listener:
 * SendWelcomeMail (queued platform mail).
 */
final class TenantRegistered implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly Tenant $tenant,
        public readonly string $businessName,
        public readonly string $locale,
        public readonly ?CarbonImmutable $trialEndsAt,
    ) {}
}
