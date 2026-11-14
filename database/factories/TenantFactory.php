<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MenuTheme;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slug' => 'tenant-'.Str::lower(Str::random(10)),
            'status' => TenantStatus::Active,
            'currency' => 'TRY',
            'default_locale' => 'tr',
            'theme' => MenuTheme::Classic,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Tenant $tenant): void {
            $tenant->translations()->create([
                'locale' => $tenant->default_locale,
                'name' => fake()->company(),
            ]);
        });
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TenantStatus::Suspended,
        ]);
    }
}
