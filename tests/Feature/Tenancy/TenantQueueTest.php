<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Enums\TenantStatus;
use App\Models\Location;
use App\Models\Tenant;
use App\Tenancy\Queue\TenantQueueListener;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Jobs\RecordTenantContextJob;
use Tests\TestCase;

/**
 * Tenant context propagation through a real queue (database driver + worker).
 */
class TenantQueueTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private Location $locationA;

    private Location $locationB;

    protected function setUp(): void
    {
        parent::setUp();

        RecordTenantContextJob::$runs = [];
        $this->locationA = Location::factory()->create();
        $this->locationB = Location::factory()->create();
        $this->tenantA = $this->locationA->tenant;
        $this->tenantB = $this->locationB->tenant;
    }

    private function context(): TenantContext
    {
        return $this->app->make(TenantContext::class);
    }

    private function dispatchUnder(?Tenant $tenant, RecordTenantContextJob $job): void
    {
        $tenant !== null ? $this->context()->set($tenant) : $this->context()->clear();
        dispatch($job->onConnection('database'));
        $this->context()->clear();
    }

    private function work(): void
    {
        // The worker runs inside the PHPUnit process, so its memory check
        // (default 128 MB) would measure the whole test run; the limit is
        // not what these tests are about.
        $this->artisan('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--tries' => 1, '--sleep' => 0, '--memory' => 1024])
            ->assertSuccessful();
    }

    /**
     * @return array<string, mixed>
     */
    private function queuedPayload(): array
    {
        return json_decode(DB::table('jobs')->sole()->payload, true);
    }

    public function test_tenant_id_is_added_to_the_payload_under_a_context(): void
    {
        $this->dispatchUnder($this->tenantA, new RecordTenantContextJob('a'));

        $this->assertSame($this->tenantA->id, $this->queuedPayload()['tenant_id']);
    }

    public function test_no_tenant_id_is_added_without_a_context(): void
    {
        $this->dispatchUnder(null, new RecordTenantContextJob('central', readsTenantData: false));

        $this->assertArrayNotHasKey('tenant_id', $this->queuedPayload());
    }

    public function test_worker_runs_each_job_under_its_own_tenant_only(): void
    {
        $this->dispatchUnder($this->tenantA, new RecordTenantContextJob('a'));
        $this->dispatchUnder($this->tenantB, new RecordTenantContextJob('b'));

        $this->work();

        $this->assertSame([
            ['label' => 'a', 'tenant_id' => $this->tenantA->id, 'location_ids' => [$this->locationA->id]],
            ['label' => 'b', 'tenant_id' => $this->tenantB->id, 'location_ids' => [$this->locationB->id]],
        ], RecordTenantContextJob::$runs);
        $this->assertFalse($this->context()->check(), 'The context must be empty after the worker.');
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    public function test_job_without_tenant_cannot_read_tenant_data_and_never_inherits_a_context(): void
    {
        $this->dispatchUnder(null, new RecordTenantContextJob('central'));
        $this->context()->set($this->tenantB); // a stale context in the worker process

        $this->work();

        $this->assertSame([], RecordTenantContextJob::$runs, 'The job must not have read any tenant data.');
        $this->assertStringContainsString('TenantNotResolvedException', DB::table('failed_jobs')->sole()->exception);
    }

    public function test_job_of_a_suspended_tenant_fails_without_running(): void
    {
        $this->dispatchUnder($this->tenantA, new RecordTenantContextJob('a'));
        $this->tenantA->forceFill(['status' => TenantStatus::Suspended])->save();

        $this->work();

        $this->assertSame([], RecordTenantContextJob::$runs);
        $this->assertStringContainsString('missing, deleted or suspended', DB::table('failed_jobs')->sole()->exception);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_job_of_a_deleted_tenant_fails_without_running(): void
    {
        $this->dispatchUnder($this->tenantA, new RecordTenantContextJob('a'));
        $this->tenantA->delete();

        $this->work();

        $this->assertSame([], RecordTenantContextJob::$runs);
        $this->assertDatabaseCount('failed_jobs', 1);
    }

    public function test_tampered_tenant_id_fails_closed(): void
    {
        $this->dispatchUnder($this->tenantA, new RecordTenantContextJob('a'));
        $job = DB::table('jobs')->sole();
        $payload = json_decode($job->payload, true);
        $payload['tenant_id'] = 999999;
        DB::table('jobs')->where('id', $job->id)->update(['payload' => json_encode($payload)]);

        $this->work();

        $this->assertSame([], RecordTenantContextJob::$runs);
        $this->assertDatabaseCount('failed_jobs', 1);
    }

    public function test_context_is_cleared_even_when_the_job_throws(): void
    {
        $this->dispatchUnder($this->tenantA, new RecordTenantContextJob('a', throwsAfterwards: true));
        $this->dispatchUnder(null, new RecordTenantContextJob('central', readsTenantData: false));

        $this->work();

        $this->assertSame($this->tenantA->id, RecordTenantContextJob::$runs[0]['tenant_id']);
        $this->assertNull(RecordTenantContextJob::$runs[1]['tenant_id'], 'The next job must not inherit tenant A.');
        $this->assertFalse($this->context()->check());
    }

    public function test_listener_never_lets_a_job_inherit_a_stale_context(): void
    {
        // Outside the worker (which also resets scoped instances), the listener
        // itself must clear a context the job's payload doesn't name.
        $this->context()->set($this->tenantB);
        $job = new SyncJob($this->app, json_encode(['uuid' => 'job-1', 'job' => 'x', 'data' => []]), 'sync', 'default');
        $listener = $this->app->make(TenantQueueListener::class);

        $listener->restore(new JobProcessing('sync', $job));
        $this->assertFalse($this->context()->check(), 'A job without tenant_id must start with an empty context.');

        $listener->release(new JobProcessed('sync', $job));
        $this->assertSame($this->tenantB->id, $this->context()->id(), 'The previous context is restored afterwards.');

        $listener->release(new JobAttempted('sync', $job));
        $this->assertSame($this->tenantB->id, $this->context()->id(), 'A second after-event for the same job is a no-op.');
    }

    public function test_sync_job_restores_the_requests_own_context_afterwards(): void
    {
        $this->context()->set($this->tenantA);

        dispatch((new RecordTenantContextJob('inline'))->onConnection('sync'));

        $this->assertSame($this->tenantA->id, RecordTenantContextJob::$runs[0]['tenant_id']);
        $this->assertSame($this->tenantA->id, $this->context()->id(), 'The surrounding request context must survive.');
    }
}
