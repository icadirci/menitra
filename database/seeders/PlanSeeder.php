<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Placeholder plan used for registration trials until plan names, prices and
 * limits are defined (open decision). Idempotent.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plan = Plan::query()->updateOrCreate(
            ['code' => config('subscriptions.default_plan')],
            ['is_active' => true, 'is_public' => true, 'trial_days' => config('subscriptions.trial_days'), 'sort_order' => 0],
        );

        foreach (['tr' => 'Başlangıç', 'en' => 'Starter'] as $locale => $name) {
            $plan->translations()->updateOrCreate(['locale' => $locale], ['name' => $name]);
        }
    }
}
