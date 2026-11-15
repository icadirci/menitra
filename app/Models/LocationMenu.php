<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A menu shown at a location (location_menu). A pivot model so attach,
 * detach and changes fire model events (cache invalidation). Owner-only,
 * written through MenuService.
 */
class LocationMenu extends Pivot
{
    use BelongsToTenant;

    protected $table = 'location_menu';

    public $incrementing = true;

    /** @var list<string> */
    protected $fillable = [
        'location_id',
        'menu_id',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function hours(): HasMany
    {
        return $this->hasMany(LocationMenuHour::class, 'location_menu_id')->orderBy('day_of_week')->orderBy('starts_at');
    }
}
