<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\BillingProfileType;
use App\Models\Tenant;
use App\Models\TenantBillingProfile;
use App\Models\TenantMembership;
use App\Models\User;
use App\Rules\TurkishTaxId;
use App\Services\Billing\BillingProfileService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\BuildsBilling;
use Tests\Support\BuildsMenus;
use Tests\TestCase;

class BillingProfileTest extends TestCase
{
    use BuildsBilling, BuildsMenus, RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'yaman-kahvesi']);
    }

    /**
     * @return array<string, string|null>
     */
    private function input(array $overrides = []): array
    {
        return array_merge([
            'type' => 'individual',
            'name' => 'Ayşe Yaman',
            'tax_id' => '100 000 001 46',
            'email' => 'Fatura@Example.com',
            'phone' => '0535 000 00 00',
            'address' => 'Caferağa Mah. Moda Cad. No: 1',
            'city' => 'İstanbul',
        ], $overrides);
    }

    private function save(array $input, ?User $user = null): TenantBillingProfile
    {
        return $this->inTenant($this->tenant, fn () => app(BillingProfileService::class)->save($this->tenant, $user ?? $this->ownerOf($this->tenant), $input));
    }

    public function test_an_owner_saves_normalised_details_with_an_encrypted_tax_id(): void
    {
        $profile = $this->save($this->input());

        $this->assertSame('10000000146', $profile->tax_id);
        $this->assertSame('+905350000000', $profile->phone);
        $this->assertSame('fatura@example.com', $profile->email);

        $raw = DB::table('tenant_billing_profiles')->value('tax_id');
        $this->assertNotSame('10000000146', $raw);
        $this->assertStringNotContainsString('10000000146', json_encode($profile->toArray()));
    }

    public function test_saving_again_updates_the_single_profile(): void
    {
        $this->save($this->input());
        $this->save($this->input(['type' => 'corporate', 'name' => 'Yaman Gıda Ltd. Şti.', 'tax_id' => '1234567890', 'tax_office' => 'Kadıköy']));

        $profile = $this->inTenant($this->tenant, fn () => TenantBillingProfile::query()->sole());
        $this->assertSame(BillingProfileType::Corporate, $profile->type);
        $this->assertSame('Kadıköy', $profile->tax_office);
    }

    public function test_invalid_tax_ids_and_a_missing_tax_office_are_rejected(): void
    {
        foreach ([
            ['tax_id' => '12345678901'],
            ['tax_id' => '00000000000'],
            ['type' => 'corporate', 'tax_id' => '1234567891', 'tax_office' => 'Kadıköy'],
            ['type' => 'corporate', 'tax_id' => '1234567890'],
            ['postal_code' => 'ABC'],
        ] as $overrides) {
            try {
                $this->save($this->input($overrides));
                $this->fail('Accepted: '.json_encode($overrides));
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_only_owners_save_billing_details(): void
    {
        $manager = TenantMembership::factory()->manager()->for($this->tenant)->create()->user;

        $this->expectException(AuthorizationException::class);
        $this->save($this->input(), $manager);
    }

    public function test_the_page_is_owner_only(): void
    {
        $this->actingAs($this->ownerOf($this->tenant))->get('/app/yaman-kahvesi/subscription/billing-details')->assertOk();

        $manager = TenantMembership::factory()->manager()->for($this->tenant)->create()->user;
        $this->actingAs($manager)->get('/app/yaman-kahvesi/subscription/billing-details')->assertForbidden();
    }

    public function test_turkish_tax_id_checksums(): void
    {
        $this->assertTrue(TurkishTaxId::isTckn('10000000146'));
        $this->assertFalse(TurkishTaxId::isTckn('10000000147'));
        $this->assertFalse(TurkishTaxId::isTckn('01000000146'));
        $this->assertTrue(TurkishTaxId::isVkn('1234567890'));
        $this->assertFalse(TurkishTaxId::isVkn('1234567891'));
        $this->assertFalse(TurkishTaxId::isVkn('123456789'));
    }
}
