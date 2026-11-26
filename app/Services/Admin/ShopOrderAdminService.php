<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\AdminAuditAction;
use App\Models\Payment;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Shop\ShopOrderService;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Shop orders of every business for /admin (documented scope bypass,
 * tenancy.md § Admin bypass rules). The bypass only reads; a change first sets
 * TenantContext to the order's own tenant, goes through ShopOrderService
 * (tenant scope active), and restores the previous context. Super admins
 * only (ShopOrderPolicy::manage); every change is audited.
 */
class ShopOrderAdminService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly ShopOrderService $orders,
        private readonly AdminAudit $audit,
    ) {}

    /**
     * @return Builder<ShopOrder>
     */
    public function query(): Builder
    {
        return ShopOrder::withoutTenantScope()
            ->with(['tenant' => fn ($tenant) => $tenant->withTrashed()->with('translations')]);
    }

    /**
     * @return Collection<int, ShopOrderItem>
     */
    public function items(ShopOrder $order): Collection
    {
        return ShopOrderItem::withoutTenantScope()
            ->where('tenant_id', $order->tenant_id)
            ->where('shop_order_id', $order->getKey())
            ->orderBy('id')
            ->get();
    }

    public function payment(ShopOrder $order): ?Payment
    {
        return Payment::withoutTenantScope()
            ->where('tenant_id', $order->tenant_id)
            ->where('shop_order_id', $order->getKey())
            ->first();
    }

    public function prepare(User $actor, ShopOrder $order): void
    {
        $this->change($actor, $order, AdminAuditAction::ShopOrderPreparing, fn (ShopOrder $order) => $this->orders->prepare($order));
    }

    public function ship(User $actor, ShopOrder $order, string $carrier, ?string $trackingNumber, ?string $trackingUrl): void
    {
        $this->change($actor, $order, AdminAuditAction::ShopOrderShipped, fn (ShopOrder $order) => $this->orders->ship($order, $carrier, $trackingNumber, $trackingUrl));
    }

    public function deliver(User $actor, ShopOrder $order): void
    {
        $this->change($actor, $order, AdminAuditAction::ShopOrderDelivered, fn (ShopOrder $order) => $this->orders->deliver($order));
    }

    public function cancel(User $actor, ShopOrder $order, string $reason): void
    {
        $this->change($actor, $order, AdminAuditAction::ShopOrderCancelled, fn (ShopOrder $order) => $this->orders->cancel($order, $reason));
    }

    public function markRefunded(User $actor, ShopOrder $order): void
    {
        $this->change($actor, $order, AdminAuditAction::ShopOrderRefunded, fn (ShopOrder $order) => $this->orders->markRefunded($order));
    }

    /**
     * @param  Closure(ShopOrder): mixed  $change
     */
    private function change(User $actor, ShopOrder $order, AdminAuditAction $action, Closure $change): void
    {
        Gate::forUser($actor)->authorize('manage', $order);

        $tenant = Tenant::withTrashed()->findOrFail($order->tenant_id);
        $previous = $this->context->check() ? $this->context->tenant() : null;
        $this->context->set($tenant);

        try {
            DB::transaction(function () use ($actor, $order, $tenant, $action, $change): void {
                $change(ShopOrder::query()->findOrFail($order->getKey()));
                $this->audit->record($action, $actor, $tenant, ['order' => $order->order_number]);
            });
        } finally {
            $previous !== null ? $this->context->set($previous) : $this->context->clear();
        }
    }
}
