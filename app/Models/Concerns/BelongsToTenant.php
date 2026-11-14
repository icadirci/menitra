<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Exceptions\CrossTenantWriteException;
use App\Exceptions\TenantNotResolvedException;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For tenant-owned models. Never put `tenant_id` in $fillable.
 *
 * - Reads are limited to TenantContext by the fail-closed TenantScope.
 * - On create, `tenant_id` comes from the context. Without a context it must
 *   already be set explicitly by trusted code (factories, registration).
 * - A row can never be moved to another tenant.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);

            if ($context->check()) {
                if ($model->tenant_id !== null && (int) $model->tenant_id !== $context->id()) {
                    throw new CrossTenantWriteException('Cannot create a row for a tenant other than the current context.');
                }

                $model->tenant_id = $context->id();

                return;
            }

            if ($model->tenant_id === null) {
                throw TenantNotResolvedException::make();
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('tenant_id')) {
                throw new CrossTenantWriteException('A tenant-owned row cannot be moved to another tenant.');
            }
        });
    }

    /**
     * Only for the documented bypasses (docs/architecture/tenancy.md).
     */
    public static function withoutTenantScope(): Builder
    {
        return static::withoutGlobalScope(TenantScope::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
