<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'plan-'.fake()->unique()->lexify('????????'),
            'is_active' => true,
            'is_public' => true,
            'trial_days' => 14,
            'sort_order' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Plan $plan): void {
            $plan->translations()->create(['locale' => 'tr', 'name' => ucfirst($plan->code)]);
        });
    }
}
