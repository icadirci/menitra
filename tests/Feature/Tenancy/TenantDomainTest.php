<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Exceptions\TenantNotResolvedException;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * tenant_domains is reserved for custom domains (later phase): the schema
 * exists with its integrity rules, nothing resolves through it yet.
 */
class TenantDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_domains_are_tenant_scoped_and_lowercased(): void
    {
        [$a, $b] = Tenant::factory()->count(2)->create();
        $context = app(TenantContext::class);

        $context->set($a);
        TenantDomain::create(['domain' => '  Menu.Example.COM ']);

        $context->set($b);
        $this->assertSame(0, TenantDomain::count());

        $context->set($a);
        $domain = TenantDomain::sole();
        $this->assertSame('menu.example.com', $domain->domain);
        $this->assertSame($a->id, $domain->tenant_id);
        $this->assertFalse($domain->fresh()->is_primary);
    }

    public function test_tenant_id_is_not_mass_assignable_and_scope_fails_closed(): void
    {
        [$a, $b] = Tenant::factory()->count(2)->create();
        app(TenantContext::class)->set($a);

        $domain = TenantDomain::create(['domain' => 'a.example.com', 'tenant_id' => $b->id, 'is_primary' => true]);
        $this->assertSame($a->id, $domain->tenant_id);
        $this->assertFalse($domain->fresh()->is_primary);

        app(TenantContext::class)->clear();
        $this->expectException(TenantNotResolvedException::class);
        TenantDomain::count();
    }

    public function test_a_domain_belongs_to_one_tenant_only(): void
    {
        [$a, $b] = Tenant::factory()->count(2)->create();
        DB::table('tenant_domains')->insert(['tenant_id' => $a->id, 'domain' => 'menu.example.com']);

        $this->expectException(QueryException::class);
        DB::table('tenant_domains')->insert(['tenant_id' => $b->id, 'domain' => 'menu.example.com']);
    }

    public function test_at_most_one_primary_domain_per_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        DB::table('tenant_domains')->insert(['tenant_id' => $tenant->id, 'domain' => 'a.example.com', 'is_primary' => true]);

        $this->expectException(QueryException::class);
        DB::table('tenant_domains')->insert(['tenant_id' => $tenant->id, 'domain' => 'b.example.com', 'is_primary' => true]);
    }

    public function test_upper_case_domains_are_rejected_by_the_database(): void
    {
        $tenant = Tenant::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('tenant_domains')->insert(['tenant_id' => $tenant->id, 'domain' => 'Menu.Example.com']);
    }
}
