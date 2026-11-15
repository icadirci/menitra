<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantRole;
use Database\Factories\TenantMembershipFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * A user's membership in a business (table tenant_user).
 *
 * Not globally tenant-scoped: memberships are read from the user's side to
 * find which tenants the user may enter. Nothing about a membership is
 * mass-assignable; role and state are set explicitly by trusted code.
 */
class TenantMembership extends Model
{
    /** @use HasFactory<TenantMembershipFactory> */
    use HasFactory;

    protected $table = 'tenant_user';

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'role' => TenantRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Assigned branches. The pivot carries this membership's tenant_id, and the
     * composite FKs reject a location of any other tenant.
     */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'location_user', 'tenant_user_id', 'location_id')
            ->withPivotValue('tenant_id', $this->tenant_id)
            ->withTimestamps();
    }

    /**
     * Active membership in a non-deleted tenant, whatever the tenant's status.
     * Suspension is checked explicitly where access is granted (see
     * ResolveTenantFromMembership and User::membershipFor()).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereHas('tenant');
    }

    public function isOwner(): bool
    {
        return $this->role === TenantRole::Owner;
    }

    public function canAccessLocation(Location $location): bool
    {
        if ((int) $location->tenant_id !== (int) $this->tenant_id) {
            return false;
        }

        if ($this->role->reachesAllLocations()) {
            return true;
        }

        return $this->assignedLocationIds()->where('location_id', $location->getKey())->exists();
    }

    /**
     * Locations this membership may act on (all of the tenant's for owners).
     */
    public function accessibleLocations(): Builder
    {
        return Location::query()
            ->where('tenant_id', $this->tenant_id)
            ->unless(
                $this->role->reachesAllLocations(),
                fn (Builder $query) => $query->whereIn('id', $this->assignedLocationIds()),
            );
    }

    /**
     * Menu content rule (CLAUDE.md §3.16): owners always; a manager only when
     * the menu is attached to at least one location and every location it is
     * attached to is assigned to them. Unattached menus are owner-only.
     */
    public function canEditMenu(Menu $menu): bool
    {
        if ((int) $menu->tenant_id !== (int) $this->tenant_id) {
            return false;
        }

        if ($this->isOwner()) {
            return true;
        }

        if ($this->role !== TenantRole::Manager) {
            return false;
        }

        return Menu::query()->whereKey($menu->getKey())->editableBy($this)->exists();
    }

    public function assignedLocationIds(): QueryBuilder
    {
        return DB::table('location_user')
            ->select('location_id')
            ->where('tenant_id', $this->tenant_id)
            ->where('tenant_user_id', $this->getKey());
    }
}
