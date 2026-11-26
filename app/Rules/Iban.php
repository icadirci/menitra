<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A Turkish IBAN (TR + 24 digits) with a valid ISO 13616 mod-97 checksum.
 * Spaces are ignored; store the value through normalize().
 */
final readonly class Iban implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid(self::normalize($value))) {
            $fail(__('billing.validation.iban'));
        }
    }

    public static function normalize(string $value): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', $value));
    }

    public static function isValid(string $iban): bool
    {
        if (preg_match('/^TR\d{24}$/', $iban) !== 1) {
            return false;
        }

        // Move the country code and check digits to the end, letters become 10..35.
        $digits = '';

        foreach (str_split(substr($iban, 4).substr($iban, 0, 4)) as $char) {
            $digits .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        $remainder = 0;

        foreach (str_split($digits, 7) as $chunk) {
            $remainder = (int) ($remainder.$chunk) % 97;
        }

        return $remainder === 1;
    }
}
