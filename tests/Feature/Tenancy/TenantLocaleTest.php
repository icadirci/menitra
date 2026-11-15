<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Exceptions\PlanLimitExceededException;
use App\Exceptions\TenantLocaleException;
use App\Models\Location;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantLocale;
use App\Services\Subscription\SubscriptionService;
use App\Services\Tenancy\TenantLocaleService;
use App\Tenancy\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantLocaleTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config(['locales.supported' => ['tr' => 'Türkçe', 'en' => 'English', 'de' => 'Deutsch']]);

        $this->tenant = $this->tenantWithLanguageLimit(3);
    }

    private function tenantWithLanguageLimit(?int $limit): Tenant
    {
        $tenant = Tenant::factory()->create();
        $plan = Plan::factory()->withLimits(['max_languages' => $limit])->create();
        app(SubscriptionService::class)->startTrial($tenant, $plan);

        return $tenant;
    }

    private function service(?Tenant $tenant = null): TenantLocaleService
    {
        $this->app->make(TenantContext::class)->set($tenant ?? $this->tenant);

        return $this->app->make(TenantLocaleService::class);
    }

    public function test_a_tenant_starts_with_its_default_locale_enabled(): void
    {
        $this->app->make(TenantContext::class)->set($this->tenant);

        $this->assertSame(['tr'], $this->tenant->enabledLocales());
    }

    public function test_enable_adds_locales_in_order(): void
    {
        $service = $this->service();
        $service->enable('en');
        $service->enable('de');

        $this->assertSame(['tr', 'en', 'de'], $this->tenant->enabledLocales());
    }

    public function test_unsupported_locales_are_rejected(): void
    {
        $this->expectException(TenantLocaleException::class);
        $this->service()->enable('xx');
    }

    public function test_enabling_twice_is_rejected(): void
    {
        $service = $this->service();
        $service->enable('en');

        $this->expectException(TenantLocaleException::class);
        $service->enable('en');
    }

    public function test_the_plan_limits_the_number_of_languages(): void
    {
        $tenant = $this->tenantWithLanguageLimit(2);
        $service = $this->service($tenant);
        $service->enable('en');

        try {
            $service->enable('de');
            $this->fail('Expected PlanLimitExceededException.');
        } catch (PlanLimitExceededException) {
            $this->assertSame(['tr', 'en'], $tenant->enabledLocales());
        }
    }

    public function test_a_tenant_without_a_subscription_cannot_add_languages_but_keeps_its_own(): void
    {
        $tenant = Tenant::factory()->create();

        try {
            $this->service($tenant)->enable('en');
            $this->fail('Expected PlanLimitExceededException.');
        } catch (PlanLimitExceededException) {
            $this->assertSame(['tr'], $tenant->enabledLocales());
        }
    }

    public function test_the_default_locale_cannot_be_disabled(): void
    {
        $this->expectException(TenantLocaleException::class);
        $this->service()->disable('tr');
    }

    public function test_disabling_keeps_translations(): void
    {
        $service = $this->service();
        $service->enable('en');
        $location = Location::factory()->for($this->tenant)->create();
        $location->translations()->create(['locale' => 'en', 'name' => 'Main branch']);

        $service->disable('en');

        $this->assertSame(['tr'], $this->tenant->enabledLocales());
        $this->assertDatabaseHas('location_translations', ['location_id' => $location->id, 'locale' => 'en', 'name' => 'Main branch']);
    }

    public function test_disabling_a_locale_that_is_not_enabled_is_rejected(): void
    {
        $this->expectException(TenantLocaleException::class);
        $this->service()->disable('en');
    }

    public function test_set_default_requires_an_enabled_locale_and_updates_the_context_tenant(): void
    {
        $service = $this->service();

        try {
            $service->setDefault('en');
            $this->fail('Expected TenantLocaleException.');
        } catch (TenantLocaleException) {
            $this->assertSame('tr', $this->tenant->fresh()->default_locale);
        }

        $service->enable('en');
        $service->setDefault('en');

        $this->assertSame('en', $this->tenant->fresh()->default_locale);
        $this->assertSame('en', $this->app->make(TenantContext::class)->tenant()->default_locale);
        $this->assertSame(['en', 'tr'], $this->tenant->enabledLocales());

        // The previous default can now be disabled.
        $service->disable('tr');
        $this->assertSame(['en'], $this->tenant->enabledLocales());
    }

    public function test_reorder_requires_exactly_the_enabled_set(): void
    {
        $service = $this->service();
        $service->enable('en');
        $service->enable('de');

        $service->reorder(['de', 'tr', 'en']);
        $this->assertSame(['de', 'tr', 'en'], TenantLocale::query()->orderBy('sort_order')->pluck('locale')->all());
        // The default always comes first for consumers.
        $this->assertSame(['tr', 'de', 'en'], $this->tenant->enabledLocales());

        foreach ([['tr', 'en'], ['tr', 'en', 'de', 'de'], ['tr', 'en', 'xx']] as $invalid) {
            try {
                $service->reorder($invalid);
                $this->fail('Invalid order accepted: '.implode(',', $invalid));
            } catch (TenantLocaleException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_tenants_never_see_or_change_each_others_locales(): void
    {
        $other = $this->tenantWithLanguageLimit(3);
        $this->service($other)->enable('en');

        $this->service()->enable('de');

        $this->assertSame(['tr', 'de'], TenantLocale::query()->orderBy('sort_order')->pluck('locale')->all());

        // Disabling "en" in this tenant must not touch the other tenant's row.
        try {
            $this->service()->disable('en');
        } catch (TenantLocaleException) {
            // not enabled here
        }

        $this->assertDatabaseHas('tenant_locales', ['tenant_id' => $other->id, 'locale' => 'en']);
    }

    public function test_the_database_rejects_duplicate_locales_per_tenant(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('tenant_locales')->insert([
            'tenant_id' => $this->tenant->id,
            'locale' => 'tr',
            'sort_order' => 5,
        ]);
    }
}
