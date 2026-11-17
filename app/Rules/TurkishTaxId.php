<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\BillingProfileType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * TCKN (individual: 11 digits with the official checksum) or VKN
 * (corporate: 10 digits with the official checksum).
 */
final readonly class TurkishTaxId implements ValidationRule
{
    public function __construct(private ?BillingProfileType $type) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = is_string($value) ? $value : '';

        $valid = match ($this->type) {
            BillingProfileType::Individual => self::isTckn($value),
            BillingProfileType::Corporate => self::isVkn($value),
            null => false,
        };

        if (! $valid) {
            $fail(__('billing.validation.tax_id_'.($this->type?->value ?? 'individual')));
        }
    }

    public static function isTckn(string $value): bool
    {
        if (preg_match('/^[1-9]\d{10}$/', $value) !== 1) {
            return false;
        }

        $d = array_map('intval', str_split($value));
        $odd = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
        $even = $d[1] + $d[3] + $d[5] + $d[7];

        return (($odd * 7 - $even) % 10 + 10) % 10 === $d[9]
            && array_sum(array_slice($d, 0, 10)) % 10 === $d[10];
    }

    public static function isVkn(string $value): bool
    {
        if (preg_match('/^\d{10}$/', $value) !== 1) {
            return false;
        }

        $d = array_map('intval', str_split($value));
        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            $tmp = ($d[$i] + 9 - $i) % 10;
            $v = ($tmp * (2 ** (9 - $i))) % 9;

            if ($tmp !== 0 && $v === 0) {
                $v = 9;
            }

            $sum += $v;
        }

        return (10 - ($sum % 10)) % 10 === $d[9];
    }
}
