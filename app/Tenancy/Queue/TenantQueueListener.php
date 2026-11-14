<?php

declare(strict_types=1);

namespace App\Tenancy\Queue;

use App\Exceptions\TenantNotResolvedException;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;

/**
 * Carries the tenant context through the queue (docs/architecture/tenancy.md).
 *
 * - Dispatch: payloadFor() adds `tenant_id` to the payload while a context is active.
 * - JobProcessing (fires before the job is unserialized, so SerializesModels
 *   already runs inside the right tenant): the context is cleared first (never
 *   inherited), then restored from the payload. A missing, deleted or suspended
 *   tenant fails the job permanently; its handle() never runs.
 * - After the attempt (processed / failed / exception / attempted): the
 *   previous context is restored — empty in a worker, the request's own
 *   context for jobs run inline by the sync driver. Idempotent per job UUID,
 *   because one failing attempt can raise several of these events.
 *
 * Registered as a singleton; TenantContext is resolved per event because the
 * worker resets scoped instances between jobs.
 */
final class TenantQueueListener
{
    /** @var array<string, Tenant|null> Previous context per in-flight job UUID. */
    private array $previous = [];

    /**
     * @return array{tenant_id?: int}
     */
    public static function payloadFor(TenantContext $context): array
    {
        return $context->check() ? ['tenant_id' => $context->id()] : [];
    }

    public function restore(JobProcessing $event): void
    {
        $context = app(TenantContext::class);
        $this->previous[$this->key($event->job)] = $context->check() ? $context->tenant() : null;
        $context->clear();

        $tenantId = $event->job->payload()['tenant_id'] ?? null;

        if ($tenantId === null) {
            return;
        }

        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant instanceof Tenant || ! $tenant->isActive()) {
            $exception = new TenantNotResolvedException(sprintf(
                'Queued job [%s] belongs to tenant #%s, which is missing, deleted or suspended; it was not run.',
                $event->job->resolveName(),
                $tenantId,
            ));

            $event->job->fail($exception);

            throw $exception;
        }

        $context->set($tenant);
    }

    public function release(JobProcessed|JobFailed|JobExceptionOccurred|JobAttempted $event): void
    {
        $key = $this->key($event->job);

        if (! array_key_exists($key, $this->previous)) {
            return;
        }

        $previous = $this->previous[$key];
        unset($this->previous[$key]);

        $context = app(TenantContext::class);
        $previous !== null ? $context->set($previous) : $context->clear();
    }

    private function key(object $job): string
    {
        return $job->uuid() ?? spl_object_hash($job);
    }
}
