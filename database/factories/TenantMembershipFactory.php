<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantMembership>
 */
class TenantMembershipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'role' => TenantRole::Owner,
            'is_active' => true,
        ];
    }

    public function manager(): static
    {
        return $this->state(fn (array $attributes) => ['role' => TenantRole::Manager]);
    }

    public function branchStaff(): static
    {
        return $this->state(fn (array $attributes) => ['role' => TenantRole::BranchStaff]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
