<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * "Pay now" at registration: the trial still starts; after verifying the
 * email the owner lands on the payment page.
 */
class RegistrationPayNowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Plan::factory()->create(['code' => config('subscriptions.default_plan')]);
    }

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ayşe Yaman',
            'email' => 'ayse@example.com',
            'password' => 'a-Strong-passw0rd',
            'password_confirmation' => 'a-Strong-passw0rd',
            'business_name' => 'Yaman Kahvesi',
            'slug' => 'yaman-kahvesi',
            'terms' => '1',
        ], $overrides);
    }

    public function test_the_form_offers_trial_or_pay_now(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('name="start" value="trial"', false)
            ->assertSee('name="start" value="pay"', false)
            ->assertSee(__('billing.register.pay_now'));
    }

    public function test_pay_now_leads_to_the_payment_page_after_verification(): void
    {
        Plan::factory()->create(['code' => 'pro']);

        $this->post('/register', $this->payload(['start' => 'pay', 'plan' => 'pro']))
            ->assertRedirect('http://localhost/app/yaman-kahvesi/subscription/checkout?plan=pro');

        // Not verified yet: the panel sends the owner to the notice and remembers the page.
        $this->get('/app/yaman-kahvesi/subscription/checkout?plan=pro')->assertRedirect(route('verification.notice'));
        $this->assertSame('http://localhost/app/yaman-kahvesi/subscription/checkout?plan=pro', session('url.intended'));
    }

    public function test_the_trial_choice_keeps_the_usual_redirect(): void
    {
        $this->post('/register', $this->payload(['start' => 'trial']))->assertRedirect('/app');
        $this->assertNotNull(User::query()->where('email', 'ayse@example.com')->first());
    }

    public function test_an_unknown_start_value_is_rejected(): void
    {
        $this->post('/register', $this->payload(['start' => 'free-forever']))->assertSessionHasErrors('start');
    }
}
