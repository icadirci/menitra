<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when tenant-owned data is touched without a tenant context.
 * The tenant scope fails closed: it never returns every tenant's rows.
 */
class TenantNotResolvedException extends RuntimeException
{
    public static function make(): self
    {
        return new self('No tenant is set in TenantContext; tenant-owned data cannot be accessed.');
    }
}
