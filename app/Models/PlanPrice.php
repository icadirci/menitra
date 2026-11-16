<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingInterval;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Platform-level price of one plan for one billing interval (amount in kuruş).
 * extra_location_amount: price per branch beyond plans.included_locations for
 * the same interval (null = no per-branch pricing).
 */
class PlanPrice extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'interval',
        'amount',
        'extra_location_amount',
        'currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'interval' => BillingInterval::class,
            'amount' => 'integer',
            'extra_location_amount' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function money(): Money
    {
        return Money::fromMinor($this->amount, $this->currency);
    }

    public function extraLocationMoney(): ?Money
    {
        return $this->extra_location_amount === null ? null : Money::fromMinor($this->extra_location_amount, $this->currency);
    }
}
