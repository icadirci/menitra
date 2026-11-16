<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BillingInterval;
use App\Enums\PlanFeature;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * The public plans: Başlangıç (basic themes only), Profesyonel (every theme,
 * the recommended plan) and Çoklu Şube (every theme, unlimited branches,
 * two branches included and a charge per additional branch). Yearly prices
 * are eight months' worth (the same saving on every plan).
 *
 * Idempotent: re-running updates the same rows (by plan code) and never
 * duplicates plans, translations, prices or limits; subscriptions are never
 * touched. Changes made in /admin to these plans are overwritten on re-run.
 */
class PlanSeeder extends Seeder
{
    /** Yearly price = monthly price × this many months. */
    private const int YEARLY_MONTHS = 8;

    /**
     * Limits shared by every plan; each plan overrides what differs.
     *
     * @var array<string, int|null>
     */
    private const array BASE_LIMITS = [
        'max_locations' => 1,
        'max_menus' => 3,
        'max_items' => 100,
        'max_languages' => 2,
        'max_qr_codes' => 5,
        'max_members' => 3,
        'premium_themes' => 1,
        'custom_domain' => 0,
        'remove_branding' => 0,
        'qr_analytics' => 0,
    ];

    public function run(): void
    {
        foreach ($this->plans() as $sortOrder => $definition) {
            $this->seedPlan($sortOrder, $definition);
        }
    }

    /**
     * Monthly prices and per-branch charges in kuruş.
     *
     * @return list<array{code: string, featured: bool, monthly: int, extra_location: int|null, included_locations: int|null, limits: array<string, int|null>, translations: array<string, array{name: string, description: string}>}>
     */
    private function plans(): array
    {
        return [
            [
                // The registration default (config subscriptions.default_plan).
                'code' => config('subscriptions.default_plan'),
                'featured' => false,
                'monthly' => 30_000,
                'extra_location' => null,
                'included_locations' => null,
                'limits' => ['premium_themes' => 0],
                'translations' => [
                    'tr' => ['name' => 'Başlangıç', 'description' => 'Tek şubeli işletmeler için dijital menünün tüm temelleri.'],
                    'en' => ['name' => 'Starter', 'description' => 'Everything a single-location business needs for its digital menu.'],
                ],
            ],
            [
                'code' => 'pro',
                'featured' => true,
                'monthly' => 50_000,
                'extra_location' => null,
                'included_locations' => null,
                'limits' => [],
                'translations' => [
                    'tr' => ['name' => 'Profesyonel', 'description' => 'Tüm menü temalarıyla markanızı öne çıkarın.'],
                    'en' => ['name' => 'Professional', 'description' => 'Make your brand stand out with every menu theme.'],
                ],
            ],
            [
                'code' => 'multi-branch',
                'featured' => false,
                'monthly' => 70_000,
                'extra_location' => 20_000,
                'included_locations' => 2,
                'limits' => [
                    'max_locations' => null,
                    'max_menus' => 10,
                    'max_items' => 500,
                    'max_languages' => 3,
                    'max_qr_codes' => 50,
                    'max_members' => 15,
                ],
                'translations' => [
                    'tr' => ['name' => 'Çoklu Şube', 'description' => 'Tüm şubelerinizi tek panelden yönetin.'],
                    'en' => ['name' => 'Multi-Branch', 'description' => 'Run every branch from a single panel.'],
                ],
            ],
        ];
    }

    /**
     * @param  array{code: string, featured: bool, monthly: int, extra_location: int|null, included_locations: int|null, limits: array<string, int|null>, translations: array<string, array{name: string, description: string}>}  $definition
     */
    private function seedPlan(int $sortOrder, array $definition): void
    {
        $plan = Plan::query()->updateOrCreate(
            ['code' => $definition['code']],
            [
                'is_active' => true,
                'is_public' => true,
                'is_featured' => $definition['featured'],
                'trial_days' => config('subscriptions.trial_days'),
                'included_locations' => $definition['included_locations'],
                'sort_order' => $sortOrder,
            ],
        );

        foreach ($definition['translations'] as $locale => $translation) {
            $plan->translations()->updateOrCreate(['locale' => $locale], $translation);
        }

        $months = [BillingInterval::Monthly->value => 1, BillingInterval::Yearly->value => self::YEARLY_MONTHS];

        foreach (BillingInterval::cases() as $interval) {
            $multiplier = $months[$interval->value];

            $plan->prices()->updateOrCreate(
                ['interval' => $interval, 'currency' => config('subscriptions.currency')],
                [
                    'amount' => $definition['monthly'] * $multiplier,
                    'extra_location_amount' => $definition['extra_location'] === null ? null : $definition['extra_location'] * $multiplier,
                    'is_active' => true,
                ],
            );
        }

        foreach (array_replace(self::BASE_LIMITS, $definition['limits']) as $feature => $limit) {
            $plan->limits()->updateOrCreate(
                ['feature' => PlanFeature::from($feature)],
                ['limit_value' => $limit],
            );
        }
    }
}
