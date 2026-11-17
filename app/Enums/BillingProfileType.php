<?php

declare(strict_types=1);

namespace App\Enums;

enum BillingProfileType: string
{
    // Sole proprietor / individual: TCKN (11 digits).
    case Individual = 'individual';
    // Company: VKN (10 digits) and tax office.
    case Corporate = 'corporate';

    public function label(): string
    {
        return __("billing.profile_types.{$this->value}");
    }
}
