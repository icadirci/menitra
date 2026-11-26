<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * awaiting_payment → paid → preparing → shipped → delivered; a paid order can
 * be cancelled until it is shipped. A failed or abandoned checkout ends as
 * payment_failed (money arriving later still makes it paid).
 */
enum ShopOrderStatus: string
{
    case AwaitingPayment = 'awaiting_payment';
    case PaymentFailed = 'payment_failed';
    case Paid = 'paid';
    case Preparing = 'preparing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __("shop.statuses.{$this->value}");
    }

    public function color(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'gray',
            self::PaymentFailed, self::Cancelled => 'danger',
            self::Paid => 'warning',
            self::Preparing => 'info',
            self::Shipped => 'primary',
            self::Delivered => 'success',
        };
    }

    public function canBecome(self $to): bool
    {
        return in_array($to, match ($this) {
            self::AwaitingPayment => [self::Paid, self::PaymentFailed],
            self::PaymentFailed => [self::Paid],
            self::Paid => [self::Preparing, self::Shipped, self::Cancelled],
            self::Preparing => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered],
            self::Delivered, self::Cancelled => [],
        }, true);
    }

    /**
     * @return list<self> statuses of orders that were paid
     */
    public static function placed(): array
    {
        return [self::Paid, self::Preparing, self::Shipped, self::Delivered, self::Cancelled];
    }
}
