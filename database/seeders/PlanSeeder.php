<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PlanFeature;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Placeholder plan used for registration trials until plan names, prices and
 * limits are defined (open decision). Idempotent: re-running updates the same
 * rows and never duplicates plans, translations or limits; subscriptions are
 * never touched.
 */
class PlanSeeder extends Seeder
{
    /**
     * DEVELOPMENT PLACEHOLDERS — not a pricing or packaging decision.
     *
     * @var array<string, int|null>
     */
    private const array STARTER_LIMITS = [
        'max_locations' => 1,
        'max_menus' => 3,
        'max_items' => 100,
        'max_languages' => 2,
        'max_qr_codes' => 5,
        'premium_themes' => 0,
        'custom_domain' => 0,
        'remove_branding' => 0,
        'qr_analytics' => 0,
    ];

    public function run(): void
    {
        $plan = Plan::query()->updateOrCreate(
            ['code' => config('subscriptions.default_plan')],
            ['is_active' => true, 'is_public' => true, 'trial_days' => config('subscriptions.trial_days'), 'sort_order' => 0],
        );

        foreach (['tr' => 'Başlangıç', 'en' => 'Starter'] as $locale => $name) {
            $plan->translations()->updateOrCreate(['locale' => $locale], ['name' => $name]);
        }

        foreach (self::STARTER_LIMITS as $feature => $limit) {
            $plan->limits()->updateOrCreate(
                ['feature' => PlanFeature::from($feature)],
                ['limit_value' => $limit],
            );
        }
    }
}
