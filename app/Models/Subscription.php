<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant-owned. State changes go only through SubscriptionService, so no
 * attribute is mass-assignable.
 */
class Subscription extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'trial_ends_at' => 'immutable_datetime',
            'current_period_starts_at' => 'immutable_datetime',
            'current_period_ends_at' => 'immutable_datetime',
            'cancel_at_period_end' => 'boolean',
            'canceled_at' => 'immutable_datetime',
            'grace_ends_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class);
    }
}
