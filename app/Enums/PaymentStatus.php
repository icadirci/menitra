<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Expired = 'expired';

    public function label(): string
    {
        return __("billing.payment_statuses.{$this->value}");
    }

    /**
     * Filament badge colour.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Succeeded => 'success',
            self::Failed => 'danger',
            self::Expired => 'gray',
        };
    }

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
