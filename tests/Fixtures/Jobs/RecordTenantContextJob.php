<?php

declare(strict_types=1);

namespace Tests\Fixtures\Jobs;

use App\Models\Location;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Test-only job: records the tenant context it runs under and the tenant-owned
 * rows it can read.
 */
class RecordTenantContextJob implements ShouldQueue
{
    use Queueable;

    /** @var list<array{label: string, tenant_id: int|null, location_ids: list<int>}> */
    public static array $runs = [];

    public function __construct(
        public string $label,
        public bool $readsTenantData = true,
        public bool $throwsAfterwards = false,
    ) {}

    public function handle(TenantContext $context): void
    {
        $tenantId = $context->check() ? $context->id() : null;

        // Fail-closed: without a context this throws TenantNotResolvedException.
        $locationIds = $this->readsTenantData ? Location::query()->orderBy('id')->pluck('id')->all() : [];

        self::$runs[] = ['label' => $this->label, 'tenant_id' => $tenantId, 'location_ids' => $locationIds];

        if ($this->throwsAfterwards) {
            throw new RuntimeException('Job failed after recording.');
        }
    }
}
