<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingInterval;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Platform-level price of one plan for one billing interval (amount in kuruş).
 */
class PlanPrice extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'interval',
        'amount',
        'currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'interval' => BillingInterval::class,
            'amount' => 'integer',
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
}
