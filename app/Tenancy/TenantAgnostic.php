<?php

declare(strict_types=1);

namespace App\Tenancy;

/**
 * Marker for commands and jobs that span tenants (docs/architecture/tenancy.md
 * § Queue processing). Only they may use withoutTenantScope(), and only to find
 * tenants; the tenant-specific work runs in one tenant-scoped job per tenant.
 */
interface TenantAgnostic {}
