<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\BillingReminderType;
use App\Mail\BillingReminderMail;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\BuildsBilling;
use Tests\Support\BuildsMenus;
use Tests\TestCase;

class BillingReminderTest extends TestCase
{
    use BuildsBilling, BuildsMenus, RefreshDatabase;

    private Tenant $tenant;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->tenant = Tenant::factory()->create(['slug' => 'yaman-kahvesi']);
        $this->plan = $this->pricedPlan('starter');
        TenantMembership::factory()->for($this->tenant)->create();
        app(SubscriptionService::class)->startTrial($this->tenant, $this->plan); // 14-day trial
    }

    private function remind(): void
    {
        $this->artisan('billing:send-reminders')->assertSuccessful();
    }

    public function test_owners_are_reminded_once_per_threshold_before_the_trial_ends(): void
    {
        $owner = $this->ownerOf($this->tenant);

        $this->remind();
        Mail::assertNothingQueued();

        $this->travel(7 * 24 * 60 + 1)->minutes();
        $this->remind();
        $this->remind();
        Mail::assertQueuedCount(1);
        Mail::assertQueued(BillingReminderMail::class, fn (BillingReminderMail $mail): bool => $mail->hasTo($owner->email)
            && $mail->type === BillingReminderType::TrialEnding
            && $mail->days === 7
            && str_contains($mail->url, '/app/yaman-kahvesi/subscription/checkout'));

        $this->travel(6)->days();
        $this->remind();
        Mail::assertQueuedCount(2);
        Mail::assertQueued(BillingReminderMail::class, fn (BillingReminderMail $mail): bool => $mail->days === 1);
    }

    public function test_a_late_start_sends_only_the_closest_reminder(): void
    {
        $this->travel(13 * 24 + 1)->hours();
        $this->remind();

        Mail::assertQueuedCount(1);
        Mail::assertQueued(BillingReminderMail::class, fn (BillingReminderMail $mail): bool => $mail->days === 1);
    }

    public function test_grace_is_announced_once(): void
    {
        $this->travel(15)->days();
        $this->inTenant($this->tenant, fn () => app(SubscriptionService::class)->processDue(Subscription::query()->sole()));

        $this->remind();
        $this->remind();

        Mail::assertQueued(BillingReminderMail::class, fn (BillingReminderMail $mail): bool => $mail->type === BillingReminderType::GraceStarted);
        $this->assertSame(1, collect(Mail::queued(BillingReminderMail::class))->filter(fn (BillingReminderMail $mail): bool => $mail->type === BillingReminderType::GraceStarted)->count());
    }

    public function test_the_reminder_mail_renders_escaped_business_names(): void
    {
        $mail = new BillingReminderMail(BillingReminderType::TrialEnding, '<b>Kafe</b>', 'Başlangıç', now()->toImmutable()->addDay(), 1, 'https://example.test/pay');

        $html = $mail->render();

        $this->assertStringContainsString('&lt;b&gt;Kafe&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>Kafe</b>', $html);
    }
}
