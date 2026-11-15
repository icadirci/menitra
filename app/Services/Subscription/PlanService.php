<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Enums\BillingInterval;
use App\Enums\PlanFeature;
use App\Models\Plan;
use App\Services\Translations\TranslationWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates and updates platform plans (used by the /admin Plan resource).
 *
 * - `code` is set once on creation and never changed (config and
 *   subscriptions refer to it).
 * - Translations go through TranslationWriter::syncPlatform().
 * - Prices: one row per interval in the platform currency. Clearing a price
 *   deactivates its row (never deleted: subscriptions may reference it).
 * - Limits: every PlanFeature gets a row. Count features: null = unlimited;
 *   boolean features: 1 = included, 0 = not included.
 * - At most one plan is featured ("most popular").
 */
final class PlanService
{
    public function __construct(private readonly TranslationWriter $translations) {}

    /**
     * @param  array{code?: string, is_active?: bool, is_public?: bool, is_featured?: bool, trial_days?: int|null, sort_order?: int|null}  $attributes
     * @param  array<string, mixed>  $translations  [locale => [name, description]]
     * @param  array<string, int|null>  $prices  BillingInterval value => amount in kuruş (null = no price)
     * @param  array<string, mixed>  $limits  PlanFeature value => limit
     *
     * @throws ValidationException
     */
    public function save(Plan $plan, array $attributes, array $translations, array $prices, array $limits): Plan
    {
        return DB::transaction(function () use ($plan, $attributes, $translations, $prices, $limits): Plan {
            $fill = [
                'is_active' => (bool) ($attributes['is_active'] ?? true),
                'is_public' => (bool) ($attributes['is_public'] ?? true),
                'is_featured' => (bool) ($attributes['is_featured'] ?? false),
                'trial_days' => isset($attributes['trial_days']) ? (int) $attributes['trial_days'] : null,
                'sort_order' => (int) ($attributes['sort_order'] ?? 0),
            ];

            if (! $plan->exists) {
                $fill['code'] = (string) $attributes['code'];
            }

            $plan->fill($fill)->save();

            if ($plan->is_featured) {
                Plan::query()->whereKeyNot($plan->getKey())->where('is_featured', true)->update(['is_featured' => false]);
            }

            $this->translations->syncPlatform($plan, $translations, ['name']);
            $this->syncPrices($plan, $prices);
            $this->syncLimits($plan, $limits);

            return $plan->refresh();
        });
    }

    /**
     * @param  array<string, int|null>  $prices
     */
    private function syncPrices(Plan $plan, array $prices): void
    {
        $currency = config('subscriptions.currency');

        foreach (BillingInterval::cases() as $interval) {
            $amount = $prices[$interval->value] ?? null;
            $existing = $plan->prices()->where('interval', $interval)->where('currency', $currency)->first();

            if ($amount === null) {
                $existing?->update(['is_active' => false]);

                continue;
            }

            if ($amount < 0) {
                throw ValidationException::withMessages(["prices.{$interval->value}" => __('panels.app.money.negative')]);
            }

            $plan->prices()->updateOrCreate(
                ['interval' => $interval, 'currency' => $currency],
                ['amount' => $amount, 'is_active' => true],
            );
        }

        $plan->unsetRelation('prices');
    }

    /**
     * @param  array<string, mixed>  $limits
     */
    private function syncLimits(Plan $plan, array $limits): void
    {
        foreach (PlanFeature::cases() as $feature) {
            $value = $limits[$feature->value] ?? null;

            if ($feature->isBoolean()) {
                $value = (bool) $value ? 1 : 0;
            } elseif ($value === null || $value === '') {
                $value = null;
            } elseif (! is_numeric($value) || (int) $value < 0) {
                throw ValidationException::withMessages(["limits.{$feature->value}" => __('validation.min.numeric', ['attribute' => $feature->label(), 'min' => 0])]);
            } else {
                $value = (int) $value;
            }

            $plan->limits()->updateOrCreate(['feature' => $feature], ['limit_value' => $value]);
        }

        $plan->unsetRelation('limits');
    }
}
