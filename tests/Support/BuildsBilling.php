<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\PaymentGateway;
use App\Enums\BillingInterval;
use App\Enums\BillingProfileType;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantBillingProfile;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Billing\CheckoutService;

/**
 * Plans with prices, billing profiles and started checkouts for billing tests.
 * Uses BuildsMenus::inTenant().
 */
trait BuildsBilling
{
    protected FakePaymentGateway $gateway;

    protected function fakeGateway(): FakePaymentGateway
    {
        $this->gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);

        return $this->gateway;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function pricedPlan(string $code, int $monthly = 30000, ?int $yearly = 240000, array $attributes = [], array $limits = []): Plan
    {
        $plan = Plan::factory()->withLimits(['max_locations' => null, 'max_menus' => 10, ...$limits])->create(['code' => $code, ...$attributes]);
        $plan->prices()->create(['interval' => BillingInterval::Monthly, 'amount' => $monthly, 'extra_location_amount' => $attributes['included_locations'] ?? null ? 20000 : null]);

        if ($yearly !== null) {
            $plan->prices()->create(['interval' => BillingInterval::Yearly, 'amount' => $yearly, 'extra_location_amount' => $attributes['included_locations'] ?? null ? 160000 : null]);
        }

        return $plan->fresh(['prices', 'translations']);
    }

    protected function ownerOf(Tenant $tenant): User
    {
        $membership = TenantMembership::query()->where('tenant_id', $tenant->getKey())->first()
            ?? TenantMembership::factory()->for($tenant)->create();

        return $membership->user;
    }

    protected function billingProfile(Tenant $tenant): TenantBillingProfile
    {
        return $this->inTenant($tenant, function (): TenantBillingProfile {
            $profile = new TenantBillingProfile;
            $profile->fill([
                'type' => BillingProfileType::Individual,
                'name' => 'Ayşe Yaman',
                'tax_id' => '10000000146',
                'email' => 'fatura@example.com',
                'phone' => '+905350000000',
                'address' => 'Caferağa Mah. Moda Cad. No: 1',
                'city' => 'İstanbul',
                'district' => 'Kadıköy',
            ])->save();

            return $profile;
        });
    }

    protected function startCheckout(Tenant $tenant, Plan $plan, BillingInterval $interval = BillingInterval::Monthly, int $periods = 1): Payment
    {
        return $this->inTenant($tenant, function () use ($tenant, $plan, $interval, $periods): Payment {
            app(CheckoutService::class)->start($tenant, $this->ownerOf($tenant), $plan, $interval, '85.34.78.112', 'tr', $periods);

            return Payment::query()->latest('id')->firstOrFail();
        });
    }
}
