<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\PaymentMethod;
use App\Exceptions\CheckoutNotAllowedException;
use App\Models\PaymentSetting;

/**
 * The payment methods super admins switched on in /admin (PaymentSetting).
 * Checked by CheckoutService and ShopCheckoutService before anything is
 * stored; the checkout pages only display the result.
 */
class PaymentMethods
{
    private ?PaymentSetting $settings = null;

    public function settings(): PaymentSetting
    {
        return $this->settings ??= PaymentSetting::current();
    }

    /**
     * @return list<PaymentMethod> card first
     */
    public function enabled(): array
    {
        return array_values(array_filter(PaymentMethod::cases(), fn (PaymentMethod $method): bool => $this->settings()->isEnabled($method)));
    }

    public function isEnabled(PaymentMethod $method): bool
    {
        return $this->settings()->isEnabled($method);
    }

    /**
     * @throws CheckoutNotAllowedException
     */
    public function ensureEnabled(PaymentMethod $method): void
    {
        if (! $this->isEnabled($method)) {
            throw CheckoutNotAllowedException::because('method_unavailable');
        }
    }
}
