<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\AdminAuditAction;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantSlugService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Tenant management actions of /admin (launch plan Faz 8). Every action is
 * authorized against TenantPolicy for the acting user, runs in a transaction
 * and is written to admin_audit_events. Only Tenant itself is changed:
 * memberships, content and subscriptions are untouched, and nothing is
 * hard-deleted. Resolution cache entries are cleared after commit by
 * MenuCacheObserver.
 */
final class TenantAdminService
{
    public function __construct(
        private readonly AdminAudit $audit,
        private readonly TenantSlugService $slugs,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function suspend(User $actor, Tenant $tenant, ?string $reason = null): void
    {
        Gate::forUser($actor)->authorize('suspend', $tenant);

        DB::transaction(function () use ($actor, $tenant, $reason): void {
            $tenant->forceFill(['status' => TenantStatus::Suspended])->save();
            $this->audit->record(AdminAuditAction::TenantSuspended, $actor, $tenant, array_filter([
                'reason' => filled($reason) ? mb_substr(trim($reason), 0, 500) : null,
            ]));
        });
    }

    /**
     * @throws AuthorizationException
     */
    public function unsuspend(User $actor, Tenant $tenant): void
    {
        Gate::forUser($actor)->authorize('unsuspend', $tenant);

        DB::transaction(function () use ($actor, $tenant): void {
            $tenant->forceFill(['status' => TenantStatus::Active])->save();
            $this->audit->record(AdminAuditAction::TenantUnsuspended, $actor, $tenant);
        });
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function changeSlug(User $actor, Tenant $tenant, string $slug): void
    {
        Gate::forUser($actor)->authorize('changeSlug', $tenant);

        DB::transaction(function () use ($actor, $tenant, $slug): void {
            $old = $this->slugs->change($tenant, $slug);
            $this->audit->record(AdminAuditAction::TenantSlugChanged, $actor, $tenant, [
                'from' => $old,
                'to' => $tenant->slug,
            ]);
        });
    }

    /**
     * Soft delete: members lose access, the public menu is gone (404), the
     * slug stays taken. Data is kept and can be restored.
     *
     * @throws AuthorizationException
     */
    public function delete(User $actor, Tenant $tenant): void
    {
        Gate::forUser($actor)->authorize('delete', $tenant);

        DB::transaction(function () use ($actor, $tenant): void {
            $tenant->delete();
            $this->audit->record(AdminAuditAction::TenantDeleted, $actor, $tenant);
        });
    }

    /**
     * @throws AuthorizationException
     */
    public function restore(User $actor, Tenant $tenant): void
    {
        Gate::forUser($actor)->authorize('restore', $tenant);

        DB::transaction(function () use ($actor, $tenant): void {
            $tenant->restore();
            $this->audit->record(AdminAuditAction::TenantRestored, $actor, $tenant);
        });
    }
}
