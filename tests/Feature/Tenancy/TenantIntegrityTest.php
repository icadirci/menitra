<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Location;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_rejects_assigning_a_branch_of_another_tenant(): void
    {
        $membershipA = TenantMembership::factory()->branchStaff()->create();
        $locationB = Location::factory()->create();

        $this->expectException(QueryException::class);

        // Even raw SQL with a "matching" tenant_id for either side is rejected.
        DB::table('location_user')->insert([
            'tenant_id' => $membershipA->tenant_id,
            'tenant_user_id' => $membershipA->id,
            'location_id' => $locationB->id,
        ]);
    }

    public function test_database_rejects_a_spoofed_tenant_id_on_the_assignment(): void
    {
        $membershipA = TenantMembership::factory()->branchStaff()->create();
        $locationB = Location::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('location_user')->insert([
            'tenant_id' => $locationB->tenant_id,
            'tenant_user_id' => $membershipA->id,
            'location_id' => $locationB->id,
        ]);
    }

    public function test_assigning_a_branch_of_the_same_tenant_works(): void
    {
        $tenant = Tenant::factory()->create();
        $membership = TenantMembership::factory()->branchStaff()->for($tenant)->create();
        $location = Location::factory()->for($tenant)->create();

        $membership->locations()->attach($location);

        $this->assertDatabaseHas('location_user', [
            'tenant_id' => $tenant->id,
            'tenant_user_id' => $membership->id,
            'location_id' => $location->id,
        ]);
    }

    public function test_a_tenant_has_at_most_one_default_location(): void
    {
        $tenant = Tenant::factory()->create();
        Location::factory()->for($tenant)->create(['is_default' => true]);

        $this->expectException(QueryException::class);
        Location::factory()->for($tenant)->create(['is_default' => true]);
    }

    public function test_a_user_has_at_most_one_membership_per_tenant(): void
    {
        $membership = TenantMembership::factory()->create();

        $this->expectException(QueryException::class);
        TenantMembership::factory()->manager()->create([
            'tenant_id' => $membership->tenant_id,
            'user_id' => $membership->user_id,
        ]);
    }
}
