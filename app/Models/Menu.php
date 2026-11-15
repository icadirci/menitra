<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MenuType;
use App\Enums\TenantRole;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasTranslations;
use App\Models\Translations\MenuTranslation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tenant-owned menu, shareable across the tenant's locations.
 */
class Menu extends Model
{
    use BelongsToTenant, HasFactory, HasTranslations, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'type',
        'is_published',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => MenuType::class,
            'is_published' => 'boolean',
        ];
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'location_menu')
            // tenant_id is set by the LocationMenu pivot model (BelongsToTenant), so eager loading works.
            ->using(LocationMenu::class)
            ->withPivot(['id', 'is_active', 'sort_order'])
            ->withTimestamps();
    }

    public function locationMenus(): HasMany
    {
        return $this->hasMany(LocationMenu::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(MenuCategory::class)->orderBy('sort_order')->orderBy('id');
    }

    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(MenuItem::class, MenuCategory::class);
    }

    /**
     * Menus this membership may edit (see TenantMembership::canEditMenu()).
     */
    public function scopeEditableBy(Builder $query, TenantMembership $membership): Builder
    {
        if ($membership->isOwner()) {
            return $query->where('menus.tenant_id', $membership->tenant_id);
        }

        if ($membership->role !== TenantRole::Manager) {
            return $query->whereRaw('1 = 0');
        }

        $assigned = $membership->assignedLocationIds();

        return $query
            ->where('menus.tenant_id', $membership->tenant_id)
            ->whereExists(fn ($sub) => $sub->from('location_menu')->whereColumn('location_menu.menu_id', 'menus.id'))
            ->whereNotExists(fn ($sub) => $sub->from('location_menu')
                ->whereColumn('location_menu.menu_id', 'menus.id')
                ->whereNotIn('location_menu.location_id', $assigned));
    }

    protected function translationModel(): string
    {
        return MenuTranslation::class;
    }
}
