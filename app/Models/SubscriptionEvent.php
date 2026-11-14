<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit trail of subscription transitions.
 */
class SubscriptionEvent extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'type' => SubscriptionEventType::class,
            'from_status' => SubscriptionStatus::class,
            'to_status' => SubscriptionStatus::class,
            'meta' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
