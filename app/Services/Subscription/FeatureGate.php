<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Enums\PlanFeature;
use App\Enums\SubscriptionStatus;
use App\Exceptions\CrossTenantWriteException;
use App\Exceptions\PlanLimitExceededException;
use App\Models\Location;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\QrCode;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantMembership;
use App\Tenancy\TenantContext;
use LogicException;

/**
 * The only place plan limits are evaluated (docs/architecture/subscriptions.md).
 *
 * - The plan of the live subscription applies while it is trialing, active,
 *   past_due or grace. Without one (expired or none) nothing new may be
 *   created and boolean features are off.
 * - It restricts creation and feature access only; viewing, editing and
 *   deleting existing records are never blocked here.
 * - Enforced by the backend services that create resources
 *   (ensureCanCreate()); UI hints are informational.
 *
 * Scoped singleton: limits are memoised per request/job.
 */
class FeatureGate
{
    /** @var array<int, array<string, int|null>|null> tenant id => feature => limit (null entry = no applicable plan) */
    private array $limits = [];

    public function __construct(private readonly TenantContext $context) {}

    /**
     * null = unlimited; 0 = not included (or no applicable subscription).
     */
    public function limit(Tenant $tenant, PlanFeature $feature): ?int
    {
        $limits = $this->limitsFor($tenant);

        if ($limits === null || ! array_key_exists($feature->value, $limits)) {
            return 0;
        }

        return $limits[$feature->value];
    }

    /**
     * Boolean features: enabled. Count features: one more row fits
     * ($currentCount defaults to the current usage).
     */
    public function allows(Tenant $tenant, PlanFeature $feature, ?int $currentCount = null): bool
    {
        $limit = $this->limit($tenant, $feature);

        if ($feature->isBoolean()) {
            return $limit === null || $limit >= 1;
        }

        if ($limit === null) {
            return true;
        }

        return ($currentCount ?? $this->usage($tenant, $feature)) < $limit;
    }

    /**
     * @throws PlanLimitExceededException
     */
    public function ensureCanCreate(Tenant $tenant, PlanFeature $feature): void
    {
        if (! $this->allows($tenant, $feature)) {
            throw new PlanLimitExceededException($feature, $this->limit($tenant, $feature));
        }
    }

    /**
     * Count features have a countable usage.
     */
    public function tracksUsage(PlanFeature $feature): bool
    {
        return ! $feature->isBoolean();
    }

    /**
     * Current number of rows for a count feature. Soft-deleted rows don't
     * count (restoring one is a creation and must be checked).
     */
    public function usage(Tenant $tenant, PlanFeature $feature): int
    {
        $this->assertCurrentTenant($tenant);

        return match ($feature) {
            PlanFeature::MaxLocations => Location::query()->count(),
            PlanFeature::MaxMenus => Menu::query()->count(),
            PlanFeature::MaxLanguages => $tenant->locales()->count(),
            PlanFeature::MaxItems => MenuItem::query()->count(),
            PlanFeature::MaxQrCodes => QrCode::query()->count(),
            PlanFeature::MaxMembers => $this->memberUsage($tenant),
            default => throw new LogicException("[{$feature->value}] is not a count feature."),
        };
    }

    /**
     * Active memberships (the owner included) plus pending invitations, in
     * one query. Memberships aren't tenant-scoped, hence the explicit
     * tenant_id; invitations go through the tenant scope.
     */
    private function memberUsage(Tenant $tenant): int
    {
        $row = TenantMembership::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('is_active', true)
            ->selectRaw('count(*) as members')
            ->selectSub(TenantInvitation::query()->pending()->selectRaw('count(*)'), 'invitations')
            ->toBase()
            ->first();

        return (int) $row->members + (int) $row->invitations;
    }

    /**
     * Forget memoised limits (after a plan or subscription change in the same request).
     */
    public function flush(): void
    {
        $this->limits = [];
    }

    /**
     * @return array<string, int|null>|null
     */
    private function limitsFor(Tenant $tenant): ?array
    {
        $this->assertCurrentTenant($tenant);

        $key = (int) $tenant->getKey();

        if (! array_key_exists($key, $this->limits)) {
            $subscription = Subscription::query()
                ->whereNull('ended_at')
                ->whereIn('status', [
                    SubscriptionStatus::Trialing,
                    SubscriptionStatus::Active,
                    SubscriptionStatus::PastDue,
                    SubscriptionStatus::Grace,
                ])
                ->with('plan.limits')
                ->first();

            $this->limits[$key] = $subscription?->plan?->limits
                ->mapWithKeys(fn ($limit): array => [$limit->feature->value => $limit->limit_value])
                ->all();
        }

        return $this->limits[$key];
    }

    /**
     * Subscriptions and usage are read through the tenant scope, so the gate
     * only answers for the tenant in TenantContext.
     */
    private function assertCurrentTenant(Tenant $tenant): void
    {
        if (! $this->context->check() || $this->context->id() !== (int) $tenant->getKey()) {
            throw new CrossTenantWriteException('FeatureGate can only evaluate the tenant in the current TenantContext.');
        }
    }
}
