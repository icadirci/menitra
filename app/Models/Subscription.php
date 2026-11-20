<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
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

    public function isLive(): bool
    {
        return $this->ended_at === null && $this->status !== SubscriptionStatus::Expired;
    }

    /**
     * A free period granted by the platform (SubscriptionService::startComplimentary()):
     * active on a non-public plan with no price and no provider. Nothing was
     * paid, so there is nothing to renew or prorate; paying starts a new paid
     * period.
     */
    public function isComplimentary(): bool
    {
        return $this->ended_at === null
            && $this->status === SubscriptionStatus::Active
            && $this->plan_price_id === null
            && $this->provider === null
            && $this->plan?->is_public === false;
    }

    /**
     * Until when the public menu stays online (docs/architecture/subscriptions.md):
     * trial or period end plus the grace period, the grace end while in grace,
     * null once expired or ended.
     */
    public function accessUntil(): ?CarbonImmutable
    {
        if (! $this->isLive()) {
            return null;
        }

        $graceDays = (int) config('subscriptions.grace_days');

        return match ($this->status) {
            SubscriptionStatus::Trialing => $this->trial_ends_at?->addDays($graceDays),
            SubscriptionStatus::Active, SubscriptionStatus::PastDue => $this->current_period_ends_at?->addDays($graceDays),
            SubscriptionStatus::Grace => $this->grace_ends_at,
            SubscriptionStatus::Expired => null,
        };
    }
}
