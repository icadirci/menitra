<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\AdminAuditAction;
use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\ShopOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\PaymentProcessor;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Bank transfers of every business for /admin (documented scope bypass,
 * tenancy.md § Admin bypass rules). The bypass only reads; approving or
 * rejecting first sets TenantContext to the payment's own tenant, goes
 * through PaymentProcessor (tenant scope active; SubscriptionService or
 * ShopOrderService apply it), and restores the previous context. Super
 * admins only (PaymentPolicy::review); every decision is audited.
 */
class PaymentAdminService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly PaymentProcessor $processor,
        private readonly AdminAudit $audit,
    ) {}

    /**
     * Bank transfer payments with their tenant, plan and the shop order's number.
     *
     * @return Builder<Payment>
     */
    public function bankTransfers(): Builder
    {
        return Payment::withoutTenantScope()
            ->where('provider', PaymentMethod::BANK_TRANSFER_PROVIDER)
            ->addSelect([
                'shop_order_number' => ShopOrder::withoutTenantScope()
                    ->select('order_number')
                    ->whereColumn('shop_orders.id', 'payments.shop_order_id')
                    ->whereColumn('shop_orders.tenant_id', 'payments.tenant_id')
                    ->limit(1),
            ])
            ->with([
                'tenant' => fn ($tenant) => $tenant->withTrashed()->with('translations'),
                'plan.translations',
            ]);
    }

    public function approve(User $actor, Payment $payment): void
    {
        $this->decide($actor, $payment, AdminAuditAction::BankTransferApproved, [], fn (Payment $payment) => $this->processor->approveBankTransfer($payment));
    }

    public function reject(User $actor, Payment $payment, string $reason): void
    {
        $reason = mb_substr(trim($reason), 0, 255);

        $this->decide($actor, $payment, AdminAuditAction::BankTransferRejected, ['reason' => $reason], fn (Payment $payment) => $this->processor->closeBankTransfer($payment, 'transfer_rejected', $reason === '' ? null : $reason));
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  Closure(Payment): mixed  $change
     */
    private function decide(User $actor, Payment $payment, AdminAuditAction $action, array $meta, Closure $change): void
    {
        Gate::forUser($actor)->authorize('review', $payment);

        $tenant = Tenant::withTrashed()->findOrFail($payment->tenant_id);
        $previous = $this->context->check() ? $this->context->tenant() : null;
        $this->context->set($tenant);

        try {
            DB::transaction(function () use ($actor, $payment, $tenant, $action, $meta, $change): void {
                $change(Payment::query()->findOrFail($payment->getKey()));
                $this->audit->record($action, $actor, $tenant, [
                    'payment' => $payment->public_id,
                    'reference' => $payment->transfer_reference,
                    'amount' => $payment->amount,
                    ...$meta,
                ]);
            });
        } finally {
            $previous !== null ? $this->context->set($previous) : $this->context->clear();
        }
    }
}
