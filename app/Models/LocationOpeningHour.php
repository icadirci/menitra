<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One opening interval of a location on a weekday (ISO 1–7). Several rows per
 * day are allowed (split shifts); closes_at < opens_at means open past
 * midnight. Written only through LocationHoursService.
 */
class LocationOpeningHour extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'day_of_week',
        'opens_at',
        'closes_at',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
