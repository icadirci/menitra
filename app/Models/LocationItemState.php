<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-branch sold-out override of an item. is_sold_out + sold_out_until null
 * = sold out until turned back on; sold_out_until in the future = sold out
 * until then (UTC, evaluated at read time, never reset by a scheduler).
 */
class LocationItemState extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'location_id',
        'menu_item_id',
        'is_sold_out',
        'sold_out_until',
    ];

    protected function casts(): array
    {
        return [
            'is_sold_out' => 'boolean',
            'sold_out_until' => 'immutable_datetime',
        ];
    }

    public function isEffectivelySoldOut(?\DateTimeInterface $now = null): bool
    {
        if (! $this->is_sold_out) {
            return false;
        }

        return $this->sold_out_until === null || $this->sold_out_until->getTimestamp() > ($now ?? now())->getTimestamp();
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }
}
