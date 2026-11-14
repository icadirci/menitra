<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Exceptions\CrossTenantWriteException;
use App\Exceptions\TenantNotResolvedException;
use App\Models\Location;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantScopeTest extends TestCase
{
    use RefreshDatabase;

    private TenantContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = $this->app->make(TenantContext::class);
    }

    public function test_context_is_empty_by_default_and_throws_when_read(): void
    {
        $this->assertFalse($this->context->check());

        $this->expectException(TenantNotResolvedException::class);
        $this->context->id();
    }

    public function test_context_is_a_scoped_singleton(): void
    {
        $tenant = Tenant::factory()->create();
        $this->context->set($tenant);

        $this->assertSame($this->context, $this->app->make(TenantContext::class));

        $this->app->forgetScopedInstances();

        $this->assertFalse($this->app->make(TenantContext::class)->check());
    }

    public function test_querying_a_tenant_owned_model_without_context_throws(): void
    {
        Location::factory()->create();

        $this->expectException(TenantNotResolvedException::class);
        Location::query()->get();
    }

    public function test_reads_are_limited_to_the_current_tenant(): void
    {
        $a = Location::factory()->create();
        $b = Location::factory()->create();

        $this->context->set($a->tenant);

        $this->assertSame([$a->id], Location::query()->pluck('id')->all());
        $this->assertNull(Location::query()->find($b->id));
    }

    public function test_create_takes_tenant_id_from_context(): void
    {
        $tenant = Tenant::factory()->create();
        $this->context->set($tenant);

        $location = Location::create(['slug' => 'merkez']);

        $this->assertSame($tenant->id, $location->tenant_id);
    }

    public function test_tenant_id_is_not_mass_assignable(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $this->context->set($tenant);

        $location = Location::create(['slug' => 'merkez', 'tenant_id' => $other->id]);

        $this->assertSame($tenant->id, $location->tenant_id);
    }

    public function test_creating_a_row_for_another_tenant_than_the_context_throws(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $this->context->set($tenant);

        $this->expectException(CrossTenantWriteException::class);
        (new Location(['slug' => 'merkez']))->forceFill(['tenant_id' => $other->id])->save();
    }

    public function test_creating_without_context_or_explicit_tenant_throws(): void
    {
        $this->expectException(TenantNotResolvedException::class);
        Location::create(['slug' => 'merkez']);
    }

    public function test_a_row_cannot_be_moved_to_another_tenant(): void
    {
        $location = Location::factory()->create();
        $other = Tenant::factory()->create();
        $this->context->set($location->tenant);

        $this->expectException(CrossTenantWriteException::class);
        $location->forceFill(['tenant_id' => $other->id])->save();
    }
}
