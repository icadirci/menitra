<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How an owner pays (switched on and off in /admin, PaymentSetting):
 * by card on the provider's hosted page (iyzico) or by bank transfer
 * (havale/EFT), confirmed manually by a super admin.
 */
enum PaymentMethod: string
{
    case Card = 'card';
    case BankTransfer = 'bank_transfer';

    /** payments.provider of a bank transfer (a card payment stores the gateway's name). */
    public const string BANK_TRANSFER_PROVIDER = 'bank_transfer';

    public function label(): string
    {
        return __("billing.methods.{$this->value}");
    }
}
