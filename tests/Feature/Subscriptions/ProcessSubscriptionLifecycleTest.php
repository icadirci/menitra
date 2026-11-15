<?php

declare(strict_types=1);

namespace Tests\Feature\Subscriptions;

use App\Console\Commands\ProcessSubscriptionLifecycle;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Jobs\ProcessTenantSubscriptionLifecycle;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\Tenant;
use App\Services\Subscription\SubscriptionService;
use App\Tenancy\TenantAgnostic;
use App\Tenancy\TenantContext;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\BuildsMenus;
use Tests\TestCase;

/**
 * subscriptions:process-lifecycle (docs/architecture/subscriptions.md § Scheduled processing):
 * one tenant-scoped job per due tenant, idempotent, failures isolated.
 */
class ProcessSubscriptionLifecycleTest extends TestCase
{
    use BuildsMenus, RefreshDatabase;

    private Tenant $a;

    private Tenant $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = $this->subscribedTenant('tenant-a');
        $this->b = $this->subscribedTenant('tenant-b');
    }

    private function subscriptionStatus(Tenant $tenant): SubscriptionStatus
    {
        return Subscription::withoutTenantScope()->where('tenant_id', $tenant->getKey())->latest('id')->firstOrFail()->status;
    }

    private function eventCount(Tenant $tenant): int
    {
        return SubscriptionEvent::withoutTenantScope()->where('tenant_id', $tenant->getKey())->count();
    }

    private function useDatabaseQueue(): void
    {
        config(['queue.default' => 'database']);
    }

    private function work(): void
    {
        $this->artisan('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--tries' => 1, '--sleep' => 0])
            ->assertSuccessful();
    }

    public function test_the_command_is_tenant_agnostic_and_scheduled_hourly(): void
    {
        $this->assertInstanceOf(TenantAgnostic::class, app(ProcessSubscriptionLifecycle::class));

        $event = collect(app(Schedule::class)->events())
            ->first(fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'subscriptions:process-lifecycle'));

        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
    }

    public function test_it_dispatches_one_job_per_due_tenant_carrying_only_the_tenant_id(): void
    {
        $this->useDatabaseQueue();
        $this->travel(15)->days();
        $notDue = $this->subscribedTenant('not-due'); // trial started now

        $this->artisan('subscriptions:process-lifecycle')->assertSuccessful();

        $payloads = DB::table('jobs')->orderBy('id')->pluck('payload')->map(fn (string $payload): array => json_decode($payload, true));
        $this->assertSame([$this->a->getKey(), $this->b->getKey()], $payloads->pluck('tenant_id')->all());
        $this->assertSame([ProcessTenantSubscriptionLifecycle::class, ProcessTenantSubscriptionLifecycle::class], $payloads->pluck('displayName')->all());
        $this->assertFalse(app(TenantContext::class)->check(), 'The command leaves no context behind.');

        $this->work();

        $this->assertSame(SubscriptionStatus::Grace, $this->subscriptionStatus($this->a));
        $this->assertSame(SubscriptionStatus::Grace, $this->subscriptionStatus($this->b));
        $this->assertSame(SubscriptionStatus::Trialing, $this->subscriptionStatus($notDue));
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    public function test_nothing_is_dispatched_when_nothing_is_due(): void
    {
        $this->useDatabaseQueue();

        $this->artisan('subscriptions:process-lifecycle')->assertSuccessful();

        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_it_is_idempotent(): void
    {
        $this->travel(15)->days();

        $this->artisan('subscriptions:process-lifecycle')->assertSuccessful();
        $this->artisan('subscriptions:process-lifecycle')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::Grace, $this->subscriptionStatus($this->a));
        $this->assertSame(2, $this->eventCount($this->a)); // trial_started, grace_started

        $this->travel(7)->days();
        $this->artisan('subscriptions:process-lifecycle')->assertSuccessful();
        $this->artisan('subscriptions:process-lifecycle')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::Expired, $this->subscriptionStatus($this->a));
        $this->assertSame(3, $this->eventCount($this->a));
    }

    public function test_suspended_and_deleted_tenants_are_skipped_and_keep_their_data(): void
    {
        $this->useDatabaseQueue();
        $this->travel(15)->days();
        $this->a->forceFill(['status' => TenantStatus::Suspended])->save();
        $this->b->delete();

        $this->artisan('subscriptions:process-lifecycle')->assertSuccessful();

        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame(SubscriptionStatus::Trialing, $this->subscriptionStatus($this->a));
        $this->assertSame(SubscriptionStatus::Trialing, $this->subscriptionStatus($this->b));
    }

    public function test_a_job_whose_tenant_was_suspended_after_dispatch_fails_without_running(): void
    {
        $this->useDatabaseQueue();
        $this->travel(15)->days();

        $this->artisan('subscriptions:process-lifecycle')->assertSuccessful();
        $this->a->forceFill(['status' => TenantStatus::Suspended])->save();
        $this->work();

        $this->assertSame(SubscriptionStatus::Trialing, $this->subscriptionStatus($this->a));
        $this->assertSame(SubscriptionStatus::Grace, $this->subscriptionStatus($this->b));
        $this->assertStringContainsString('missing, deleted or suspended', DB::table('failed_jobs')->sole()->exception);
    }

    public function test_a_failing_tenant_does_not_stop_the_others_with_a_queue_worker(): void
    {
        $this->useDatabaseQueue();
        $this->failFor($this->a);
        $this->travel(15)->days();

        $this->artisan('subscriptions:process-lifecycle')->assertSuccessful();
        $this->work();

        $this->assertSame(SubscriptionStatus::Trialing, $this->subscriptionStatus($this->a));
        $this->assertSame(SubscriptionStatus::Grace, $this->subscriptionStatus($this->b));
        $this->assertStringContainsString('boom', DB::table('failed_jobs')->sole()->exception);
    }

    public function test_a_failing_tenant_does_not_stop_the_others_with_the_sync_driver(): void
    {
        $this->failFor($this->a);
        $this->travel(15)->days();

        $this->artisan('subscriptions:process-lifecycle')->assertFailed();

        $this->assertSame(SubscriptionStatus::Trialing, $this->subscriptionStatus($this->a));
        $this->assertSame(SubscriptionStatus::Grace, $this->subscriptionStatus($this->b));
        $this->assertFalse(app(TenantContext::class)->check());
    }

    public function test_a_tenants_job_never_touches_another_tenant(): void
    {
        $this->useDatabaseQueue();
        $this->travel(15)->days();

        $this->inTenant($this->a, function (): void {
            ProcessTenantSubscriptionLifecycle::dispatch(); // dispatched when the PendingDispatch is destroyed, still inside A
        });
        $this->work();

        $this->assertSame(SubscriptionStatus::Grace, $this->subscriptionStatus($this->a));
        $this->assertSame(SubscriptionStatus::Trialing, $this->subscriptionStatus($this->b));
        $this->assertSame(1, $this->eventCount($this->b));
    }

    public function test_a_job_without_a_tenant_context_fails_closed(): void
    {
        $this->useDatabaseQueue();
        $this->travel(15)->days();

        ProcessTenantSubscriptionLifecycle::dispatch();
        $this->work();

        $this->assertSame(SubscriptionStatus::Trialing, $this->subscriptionStatus($this->a));
        $this->assertSame(SubscriptionStatus::Trialing, $this->subscriptionStatus($this->b));
        $this->assertStringContainsString('TenantNotResolvedException', DB::table('failed_jobs')->sole()->exception);
    }

    /**
     * Makes SubscriptionService fail while the given tenant is the context.
     */
    private function failFor(Tenant $tenant): void
    {
        $failingId = (int) $tenant->getKey();

        $this->app->bind(SubscriptionService::class, fn ($app) => new class($app->make(TenantContext::class), $failingId) extends SubscriptionService
        {
            public function __construct(private readonly TenantContext $current, private readonly int $failingId)
            {
                parent::__construct($current);
            }

            public function processDue(Subscription $subscription): int
            {
                if ($this->current->id() === $this->failingId) {
                    throw new RuntimeException('boom');
                }

                return parent::processDue($subscription);
            }
        });
    }
}
