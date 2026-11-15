<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Events\TenantRegistered;
use App\Mail\WelcomeMail;
use App\Models\Plan;
use App\Services\Registration\WelcomeMessage;
use App\Services\Subscription\SubscriptionService;
use App\Services\Tenancy\TenantRegistrationService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class WelcomeMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Plan::factory()->create(['code' => config('subscriptions.default_plan'), 'trial_days' => 14]);
        Notification::fake();
    }

    private function register(string $locale = 'tr'): void
    {
        app(TenantRegistrationService::class)->register('Ayşe Yaman', 'ayse@example.com', 'secret-password', 'Yaman & Kahve', 'yaman-kahvesi', $locale);
    }

    public function test_registration_queues_a_welcome_mail_after_commit(): void
    {
        Mail::fake();

        $this->post('/register', [
            'name' => 'Ayşe Yaman',
            'email' => 'ayse@example.com',
            'password' => 'a-Strong-passw0rd',
            'password_confirmation' => 'a-Strong-passw0rd',
            'business_name' => 'Yaman Kahvesi',
            'slug' => 'yaman-kahvesi',
        ])->assertRedirect('/app');

        Mail::assertQueued(WelcomeMail::class, function (WelcomeMail $mail): bool {
            return $mail->hasTo('ayse@example.com')
                && $mail->locale === 'tr'
                && $mail->welcome->businessName === 'Yaman Kahvesi'
                && $mail->welcome->panelUrl === route('filament.app.pages.dashboard', ['tenant' => 'yaman-kahvesi'])
                && $mail->welcome->trialEndsOn !== null;
        });
        Mail::assertQueuedCount(1);
    }

    public function test_the_event_carries_the_new_business(): void
    {
        Event::fake([TenantRegistered::class]);

        $this->register('en');

        Event::assertDispatched(TenantRegistered::class, fn (TenantRegistered $event): bool => $event->tenant->slug === 'yaman-kahvesi'
            && $event->user->email === 'ayse@example.com'
            && $event->locale === 'en'
            && $event->trialEndsAt !== null);
    }

    public function test_no_mail_when_the_surrounding_transaction_rolls_back(): void
    {
        Mail::fake();

        try {
            DB::transaction(function (): void {
                $this->register();

                throw new RuntimeException('Rolled back after the registration ran.');
            });
        } catch (RuntimeException) {
            // expected
        }

        Mail::assertNothingQueued();
        $this->assertDatabaseCount('tenants', 0);
    }

    public function test_no_mail_when_the_registration_fails(): void
    {
        Mail::fake();

        $this->mock(SubscriptionService::class)
            ->shouldReceive('startTrial')
            ->andThrow(new RuntimeException('Simulated failure.'));

        try {
            $this->register();
        } catch (RuntimeException) {
            // expected
        }

        Mail::assertNothingQueued();
    }

    public function test_the_queued_job_carries_no_tenant_context_or_model(): void
    {
        Queue::fake();
        $contextWhenFired = null;
        Event::listen(TenantRegistered::class, function () use (&$contextWhenFired): void {
            $contextWhenFired = app(TenantContext::class)->check();
        });

        $this->register();

        Queue::assertPushed(SendQueuedMailable::class, fn (SendQueuedMailable $job): bool => $job->mailable instanceof WelcomeMail
            && $job->mailable->welcome instanceof WelcomeMessage);

        // Fired after commit with the registration's context already
        // restored (empty), so TenantQueueListener adds no tenant_id.
        $this->assertFalse($contextWhenFired);
        $this->assertFalse(app(TenantContext::class)->check());
    }

    public function test_the_mail_renders_in_turkish_and_english_with_escaped_business_name(): void
    {
        $message = fn (string $locale) => new WelcomeMessage('Ayşe', 'Yaman & <b>Kahve</b>', 'https://restano.app/app/yaman', '29 Kasım 2026', $locale);

        $tr = (new WelcomeMail($message('tr')))->locale('tr');
        $tr->assertSeeInHtml('Hoş geldiniz, Ayşe!');
        $tr->assertSeeInHtml('Yaman &amp; &lt;b&gt;Kahve&lt;/b&gt;', false);
        $tr->assertDontSeeInHtml('<b>Kahve</b>', false);
        $tr->assertSeeInHtml('https://restano.app/app/yaman');
        $tr->assertSeeInText('İşletme paneline git');
        $tr->assertHasSubject('Restano\'ya hoş geldiniz: Yaman & <b>Kahve</b>');

        $en = (new WelcomeMail($message('en')))->locale('en');
        $en->assertSeeInHtml('Welcome, Ayşe!');
        $en->assertSeeInText('Go to your business panel');
    }

    public function test_tr_and_en_welcome_strings_have_the_same_keys(): void
    {
        $keys = fn (string $locale): array => array_keys(Arr::dot(require lang_path("{$locale}/registration.php")));

        $this->assertSame($keys('tr'), $keys('en'));
    }
}
