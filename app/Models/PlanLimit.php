<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlanFeature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Platform-level limit of one plan for one PlanFeature (not tenant-owned).
 * Evaluated only by App\Services\Subscription\FeatureGate.
 */
class PlanLimit extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'feature',
        'limit_value',
    ];

    protected function casts(): array
    {
        return [
            'feature' => PlanFeature::class,
            'limit_value' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
