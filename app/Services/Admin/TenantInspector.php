<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Location;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Read-only cross-tenant reads for /admin tenant pages (documented scope
 * bypass, tenancy.md § Allowed scope bypasses): only the allow-listed models
 * Location, Subscription, SubscriptionEvent and Payment, always constrained
 * to one tenant. Nothing here writes.
 */
final class TenantInspector
{
    public function liveSubscription(Tenant $tenant): ?Subscription
    {
        return Subscription::withoutTenantScope()
            ->where('tenant_id', $tenant->getKey())
            ->whereNull('ended_at')
            ->with('plan.translations')
            ->latest('id')
            ->first();
    }

    /**
     * Relationship for a read-only relation manager (deleted branches excluded).
     */
    public function locations(Tenant $tenant): HasMany
    {
        return new HasMany(Location::withoutTenantScope(), $tenant, 'locations.tenant_id', 'id');
    }

    public function subscriptionEvents(Tenant $tenant): HasMany
    {
        return new HasMany(SubscriptionEvent::withoutTenantScope(), $tenant, 'subscription_events.tenant_id', 'id');
    }

    /**
     * Payments for a read-only relation manager (no billing details: the
     * table selects only allow-listed columns).
     */
    public function payments(Tenant $tenant): HasMany
    {
        return new HasMany(Payment::withoutTenantScope(), $tenant, 'payments.tenant_id', 'id');
    }
}
