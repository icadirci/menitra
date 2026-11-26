<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\CheckoutNotAllowedException;
use App\Exceptions\CrossTenantWriteException;
use App\Mail\BankTransferNotifiedMail;
use App\Models\Payment;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * The owner's side of a bank transfer (docs/architecture/subscriptions.md
 * § Bank transfers): the reference for the transfer description, "I have
 * paid" and cancelling a transfer not reported yet. Nothing here applies a
 * payment; a super admin approves or rejects it (PaymentAdminService →
 * PaymentProcessor). Owner-only, inside the payment's own TenantContext.
 */
class BankTransferService
{
    /** Unambiguous characters (no 0/O, 1/I/L) for references typed by hand. */
    private const string ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public function __construct(
        private readonly TenantContext $context,
        private readonly PaymentMethods $methods,
        private readonly PaymentProcessor $processor,
    ) {}

    /**
     * "MN7K3Q9XPA": random, written by the owner in the transfer description.
     * Uniqueness across tenants is the database's unique index.
     */
    public function newReference(): string
    {
        $reference = 'MN';

        for ($i = 0; $i < 8; $i++) {
            $reference .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $reference;
    }

    /**
     * The tenant's open transfer for a plan period ($shop = false) or a shop
     * order ($shop = true), read through the tenant scope.
     */
    public function open(bool $shop): ?Payment
    {
        return Payment::query()
            ->where('provider', PaymentMethod::BANK_TRANSFER_PROVIDER)
            ->where('status', PaymentStatus::Pending)
            ->when($shop, fn ($query) => $query->whereNotNull('shop_order_id'), fn ($query) => $query->whereNull('shop_order_id'))
            ->latest('id')
            ->first();
    }

    /**
     * While a transfer waits, no other checkout for the same purpose starts:
     * a second payment (card or transfer) would pay the same thing twice.
     *
     * @throws CheckoutNotAllowedException
     */
    public function ensureNoneOpen(bool $shop): void
    {
        if ($this->open($shop) !== null) {
            throw CheckoutNotAllowedException::because('transfer_open');
        }
    }

    /**
     * "I have paid": the platform team is told to look for the money.
     * Repeating it changes nothing.
     *
     * @throws AuthorizationException|ValidationException
     */
    public function markNotified(Tenant $tenant, User $user, Payment $payment): Payment
    {
        $this->authorize($tenant, $user, $payment);

        return DB::transaction(function () use ($tenant, $payment): Payment {
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isOpenBankTransfer()) {
                throw ValidationException::withMessages(['payment' => __('billing.bank_transfer.errors.not_open')]);
            }

            if ($locked->transfer_notified_at !== null) {
                return $locked;
            }

            $locked->forceFill(['transfer_notified_at' => CarbonImmutable::now()])->save();
            $this->mailPlatform($tenant, $locked);

            return $locked;
        });
    }

    /**
     * Withdraws a transfer the owner hasn't reported as paid; afterwards
     * only the platform team can reject it (the money may be on its way).
     *
     * @throws AuthorizationException|ValidationException
     */
    public function cancel(Tenant $tenant, User $user, Payment $payment): Payment
    {
        $this->authorize($tenant, $user, $payment);

        return $this->processor->closeBankTransfer($payment, 'cancelled', null, requireUnreported: true);
    }

    /**
     * No address, billing or contact data in the mail.
     */
    private function mailPlatform(Tenant $tenant, Payment $payment): void
    {
        $recipient = $this->methods->settings()->notification_email ?: SiteSetting::existing()?->contact_email;

        if (blank($recipient)) {
            return;
        }

        $mail = new BankTransferNotifiedMail(
            reference: (string) $payment->transfer_reference,
            businessName: $tenant->translate($tenant->default_locale)?->name ?? $tenant->slug,
            amountMinor: $payment->amount,
            currency: $payment->currency,
            url: url('/admin/bank-transfers'),
        );

        DB::afterCommit(fn () => Mail::to($recipient)->locale(config('locales.default'))->queue($mail));
    }

    /**
     * @throws AuthorizationException
     */
    private function authorize(Tenant $tenant, User $user, Payment $payment): void
    {
        if (! $this->context->check() || $this->context->id() !== (int) $tenant->getKey() || (int) $payment->tenant_id !== (int) $tenant->getKey()) {
            throw new CrossTenantWriteException('A bank transfer can only be changed in its own TenantContext.');
        }

        if (! $user->can('manageSettings', $tenant)) {
            throw new AuthorizationException;
        }
    }
}
