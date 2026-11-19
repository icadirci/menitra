<?php

declare(strict_types=1);

namespace App\Services\WaiterCalls;

use App\Enums\PlanFeature;
use App\Exceptions\CrossTenantWriteException;
use App\Models\Tenant;
use App\Services\Subscription\FeatureGate;
use App\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Waiter calls of the current tenant (docs/architecture/menu-system.md § Waiter calls).
 *
 * The tenant-wide switch is written only here. The caller authorizes
 * (TenantPolicy::manageSettings); this service enforces the plan: turning
 * calls on needs the waiter_call feature, turning them off is always
 * allowed. Calls are offered to guests only while both hold (isActive()), so
 * losing the feature hides them without touching the switch.
 */
final class WaiterCallService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly FeatureGate $gate,
    ) {}

    /**
     * @throws ValidationException
     */
    public function setEnabled(Tenant $tenant, bool $enabled): void
    {
        $this->assertCurrentTenant($tenant);

        if ($enabled === $tenant->waiter_calls_enabled) {
            return;
        }

        if ($enabled && ! $this->planAllows($tenant)) {
            throw ValidationException::withMessages(['waiter_calls_enabled' => __('panels.app.theme.waiter_calls.not_in_plan')]);
        }

        // forceFill: the switch is never mass-assignable. Saving the tenant
        // bumps the content version (MenuCacheObserver).
        $tenant->forceFill(['waiter_calls_enabled' => $enabled])->save();
    }

    public function planAllows(Tenant $tenant): bool
    {
        $this->assertCurrentTenant($tenant);

        return $this->gate->allows($tenant, PlanFeature::WaiterCall);
    }

    /**
     * Guests may call: switched on and included in the live plan.
     */
    public function isActive(Tenant $tenant): bool
    {
        return $tenant->waiter_calls_enabled && $this->planAllows($tenant);
    }

    private function assertCurrentTenant(Tenant $tenant): void
    {
        if (! $this->context->check() || $this->context->id() !== (int) $tenant->getKey()) {
            throw new CrossTenantWriteException('Waiter call settings can only be changed for the tenant in the current context.');
        }
    }
}
