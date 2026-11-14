<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Location;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'slug' => 'branch-'.Str::lower(Str::random(8)),
            'is_active' => true,
            'is_default' => false,
            'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Location $location): void {
            $location->translations()->create([
                'locale' => 'tr',
                'name' => fake()->city(),
            ]);
        });
    }
}
