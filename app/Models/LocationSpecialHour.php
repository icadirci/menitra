<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Date-specific exception to the weekly hours (e.g. Bayram): either closed
 * all day or one or more opening intervals that replace the weekly hours on
 * that date. Written only through LocationHoursService.
 */
class LocationSpecialHour extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'date',
        'is_closed',
        'opens_at',
        'closes_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'is_closed' => 'boolean',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
