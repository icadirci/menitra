<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MenuType;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasTranslations;
use App\Models\Translations\MenuTranslation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tenant-owned menu, shareable across the tenant's locations.
 */
class Menu extends Model
{
    use BelongsToTenant, HasTranslations, SoftDeletes;

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
            ->withPivotValue('tenant_id', $this->tenant_id)
            ->withPivot(['is_active', 'sort_order'])
            ->withTimestamps();
    }

    protected function translationModel(): string
    {
        return MenuTranslation::class;
    }
}
