<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Exceptions\TenantNotResolvedException;
use App\Models\Tenant;

/**
 * The only source of the current tenant. Registered as a scoped singleton,
 * so it is reset per request and per queued job.
 *
 * It is filled only after the tenant has been authorized, e.g. by
 * ResolveTenantFromMembership in the business panel.
 */
final class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * @throws TenantNotResolvedException
     */
    public function tenant(): Tenant
    {
        return $this->tenant ?? throw TenantNotResolvedException::make();
    }

    /**
     * @throws TenantNotResolvedException
     */
    public function id(): int
    {
        return $this->tenant()->getKey();
    }
}
