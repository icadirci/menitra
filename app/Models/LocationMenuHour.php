<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * When a menu is served at a location (optional; none = all day). Used only
 * for the "now serving" hint.
 */
class LocationMenuHour extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'day_of_week',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return ['day_of_week' => 'integer'];
    }

    public function locationMenu(): BelongsTo
    {
        return $this->belongsTo(LocationMenu::class);
    }
}
