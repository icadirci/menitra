<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\BillingInterval;
use App\Enums\PlanFeature;
use App\Filament\Admin\Resources\Plans\Pages\CreatePlan;
use App\Filament\Admin\Resources\Plans\Pages\EditPlan;
use App\Models\Plan;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * /admin plan resource: super admins only; saved through PlanService.
 */
class PlanAdminTest extends TestCase
{
    use RefreshDatabase;

    private function asSuperAdmin(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        Filament::setCurrentPanel('admin');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(array $overrides = []): array
    {
        $limits = [];
        foreach (PlanFeature::cases() as $feature) {
            $limits[$feature->value] = $feature->isBoolean() ? false : 1;
        }

        return array_replace_recursive([
            'code' => 'pro',
            'is_active' => true,
            'is_public' => true,
            'is_featured' => true,
            'trial_days' => 14,
            'sort_order' => 1,
            'translations' => [
                'tr' => ['name' => 'Profesyonel', 'description' => 'Markasını öne çıkaranlar için.'],
                'en' => ['name' => 'Professional', 'description' => ''],
            ],
            'prices' => ['monthly' => '499', 'yearly' => '4990.00'],
            'limits' => $limits,
        ], $overrides);
    }

    public function test_only_super_admins_can_manage_plans(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())->get('/admin/plans')->assertOk();
        $this->actingAs(User::factory()->platformStaff()->create())->get('/admin/plans')->assertForbidden();
        $this->actingAs(User::factory()->platformStaff()->create())->get('/admin/plans/create')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/admin/plans')->assertForbidden();
    }

    public function test_plans_cannot_be_deleted(): void
    {
        $this->assertFalse(User::factory()->superAdmin()->create()->can('delete', Plan::factory()->create()));
    }

    public function test_a_plan_is_created_with_prices_limits_and_translations(): void
    {
        $this->asSuperAdmin();

        Livewire::test(CreatePlan::class)
            ->fillForm($this->formData(['limits' => ['max_locations' => '', 'premium_themes' => true]]))
            ->call('create')
            ->assertHasNoFormErrors();

        $plan = Plan::query()->where('code', 'pro')->with(['prices', 'limits', 'translations'])->sole();

        $this->assertTrue($plan->is_featured);
        $this->assertSame('Professional', $plan->translate('en')->name);
        $this->assertSame(49900, $plan->priceFor(BillingInterval::Monthly)->amount);
        $this->assertSame(499000, $plan->priceFor(BillingInterval::Yearly)->amount);
        $this->assertSame('TRY', $plan->priceFor(BillingInterval::Monthly)->currency);
        $this->assertNull($plan->limits->firstWhere('feature', PlanFeature::MaxLocations)->limit_value, 'Empty count limit = unlimited.');
        $this->assertSame(1, $plan->limits->firstWhere('feature', PlanFeature::PremiumThemes)->limit_value);
        $this->assertSame(0, $plan->limits->firstWhere('feature', PlanFeature::RemoveBranding)->limit_value);
        $this->assertCount(count(PlanFeature::cases()), $plan->limits);
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->asSuperAdmin();
        Plan::factory()->create(['code' => 'taken']);

        Livewire::test(CreatePlan::class)
            ->fillForm($this->formData(['code' => 'taken', 'prices' => ['monthly' => '-5'], 'limits' => ['max_menus' => '-1']]))
            ->call('create')
            ->assertHasFormErrors(['code', 'prices.monthly', 'limits.max_menus']);

        Livewire::test(CreatePlan::class)
            ->fillForm($this->formData(['code' => 'Not A Slug', 'translations' => ['tr' => ['name' => '']]]))
            ->call('create')
            ->assertHasFormErrors(['code', 'translations.tr.name']);
    }

    public function test_editing_keeps_the_code_and_featured_is_exclusive(): void
    {
        $this->asSuperAdmin();
        $other = Plan::factory()->create(['code' => 'business', 'is_featured' => true]);
        $plan = Plan::factory()->create(['code' => 'starter']);
        $plan->translations()->updateOrCreate(['locale' => 'tr'], ['name' => 'Başlangıç']);
        $plan->prices()->create(['interval' => BillingInterval::Yearly, 'amount' => 100000]);

        Livewire::test(EditPlan::class, ['record' => $plan->getRouteKey()])
            ->assertSchemaStateSet(['translations.tr.name' => 'Başlangıç', 'prices.yearly' => '1000.00'])
            ->fillForm(['code' => 'hacked', 'is_featured' => true, 'prices' => ['monthly' => '99', 'yearly' => '']])
            ->call('save')
            ->assertHasNoFormErrors();

        $plan->refresh()->load('prices');
        $this->assertSame('starter', $plan->code);
        $this->assertTrue($plan->is_featured);
        $this->assertFalse($other->fresh()->is_featured);
        $this->assertSame(9900, $plan->priceFor(BillingInterval::Monthly)->amount);
        $this->assertNull($plan->priceFor(BillingInterval::Yearly), 'A cleared price is no longer offered.');
        $this->assertFalse($plan->prices->firstWhere('interval', BillingInterval::Yearly)->is_active, 'Cleared prices are deactivated, not deleted.');
    }

    public function test_edit_form_keeps_missing_limits_as_not_included(): void
    {
        $this->asSuperAdmin();
        $plan = Plan::factory()->withLimits(['max_menus' => null])->create();
        $plan->translations()->updateOrCreate(['locale' => 'tr'], ['name' => 'X']);

        Livewire::test(EditPlan::class, ['record' => $plan->getRouteKey()])
            ->assertSchemaStateSet(['limits.max_menus' => null, 'limits.max_items' => 0, 'limits.premium_themes' => false]);
    }
}
