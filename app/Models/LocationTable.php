<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A table of a branch. Its QR code (at most one) tells which table a guest
 * sits at. Written only through App\Services\Locations\LocationTableService;
 * the branch never changes.
 */
class LocationTable extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'location_id',
        'name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function qrCode(): HasOne
    {
        return $this->hasOne(QrCode::class);
    }
}
