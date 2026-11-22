<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingInterval;
use App\Enums\PaymentKind;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant-owned record of one checkout (docs/architecture/subscriptions.md § Payments).
 * Written only by CheckoutService and PaymentProcessor, so nothing is
 * mass-assignable. Amounts are kuruş and computed on the server.
 * billing_details is an encrypted snapshot of the billing profile.
 */
class Payment extends Model
{
    use BelongsToTenant, HasUlids;

    /** @var list<string> */
    protected $fillable = [];

    /** @var list<string> */
    protected $hidden = ['billing_details', 'provider_token'];

    protected function casts(): array
    {
        return [
            'interval' => BillingInterval::class,
            'period_count' => 'integer',
            'kind' => PaymentKind::class,
            'status' => PaymentStatus::class,
            'base_amount' => 'integer',
            'extra_locations' => 'integer',
            'extra_location_amount' => 'integer',
            'amount' => 'integer',
            'credit_amount' => 'integer',
            'prorated_days' => 'integer',
            'billing_details' => 'encrypted:array',
            'period_starts_at' => 'immutable_datetime',
            'period_ends_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }

    /**
     * Only `public_id` is a ULID; the primary key stays an incrementing bigint.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class, 'plan_price_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * "Yearly", "3 years", "Monthly": the interval and how many periods it covers.
     */
    public function periodLabel(): string
    {
        return $this->interval->periodLabel(max(1, (int) $this->period_count));
    }

    public function money(): Money
    {
        return Money::fromMinor($this->amount, $this->currency);
    }

    public function baseMoney(): Money
    {
        return Money::fromMinor($this->base_amount, $this->currency);
    }

    public function extraLocationsMoney(): Money
    {
        return Money::fromMinor($this->extra_locations * (int) $this->extra_location_amount, $this->currency);
    }

    public function creditMoney(): ?Money
    {
        return $this->credit_amount === null ? null : Money::fromMinor($this->credit_amount, $this->currency);
    }

    public function isUpgrade(): bool
    {
        return $this->kind === PaymentKind::Upgrade;
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }

    public function isSucceeded(): bool
    {
        return $this->status === PaymentStatus::Succeeded;
    }
}
