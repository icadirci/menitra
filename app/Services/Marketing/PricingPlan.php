<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Enums\PlanFeature;
use App\Support\Money;
use Illuminate\Support\Number;

/**
 * Display data of one public plan, already resolved for the current locale.
 *
 * $limits mirrors plan_limits: feature value => limit_value (null = unlimited,
 * 0/1 for boolean features); a feature without an entry is not included.
 * These are display values only — enforcement stays in FeatureGate.
 */
final readonly class PricingPlan
{
    /**
     * @param  array<string, int|null>  $limits
     */
    public function __construct(
        public string $code,
        public string $name,
        public ?string $description,
        public bool $featured,
        public Money $monthly,
        public ?Money $yearly,
        public ?Money $yearlyPerMonth,
        public ?int $yearlySavingPercent,
        public array $limits = [],
        public ?int $trialDays = null,
    ) {}

    /**
     * Included: the feature is built, and a limit row exists and is unlimited
     * or at least 1. Unbuilt features are never advertised, whatever the row says.
     */
    public function includes(PlanFeature $feature): bool
    {
        if (! $feature->isAvailable() || ! array_key_exists($feature->value, $this->limits)) {
            return false;
        }

        $limit = $this->limits[$feature->value];

        return $limit === null || $limit >= 1;
    }

    public function isUnlimited(PlanFeature $feature): bool
    {
        return ! $feature->isBoolean()
            && array_key_exists($feature->value, $this->limits)
            && $this->limits[$feature->value] === null;
    }

    public function limit(PlanFeature $feature): ?int
    {
        return $this->limits[$feature->value] ?? null;
    }

    /**
     * Comparison table cell text of an included count feature, e.g. "100" or "Sınırsız".
     */
    public function limitText(PlanFeature $feature): string
    {
        return $this->isUnlimited($feature)
            ? __('plans.unlimited')
            : (string) Number::format((int) $this->limit($feature), locale: app()->getLocale());
    }

    /**
     * Plan card bullet points, built only from included features
     * (e.g. "3 menus", "Unlimited products", "Premium themes").
     *
     * @return list<string>
     */
    public function highlights(): array
    {
        $points = [];

        foreach (PlanFeature::cases() as $feature) {
            if (! $this->includes($feature)) {
                continue;
            }

            $points[] = match (true) {
                $feature->isBoolean() => $feature->label(),
                $this->isUnlimited($feature) => __("plans.allowance_unlimited.{$feature->value}"),
                default => trans_choice("plans.allowance.{$feature->value}", (int) $this->limit($feature), [
                    'count' => Number::format((int) $this->limit($feature), locale: app()->getLocale()),
                ]),
            };
        }

        return $points;
    }
}
