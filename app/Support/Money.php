<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use NumberFormatter;

/**
 * An amount of money in minor units (kuruş for TRY). Storage and arithmetic
 * never use floats; decimal input is parsed as a string.
 */
final readonly class Money
{
    /**
     * Whole-unit digits accepted from decimal input, so that `units * 100`
     * always fits into a signed 64-bit integer (bigint columns).
     */
    private const int MAX_WHOLE_DIGITS = 16;

    private function __construct(
        public int $minor,
        public string $currency,
    ) {}

    public static function fromMinor(int $minor, string $currency = 'TRY'): self
    {
        return new self($minor, self::normalizeCurrency($currency));
    }

    /**
     * Parses user input such as "85", "85.5", "85,50" or "-2.25". One decimal
     * separator ("." or ","), at most two fraction digits, no thousands
     * separators (they are ambiguous between tr and en).
     *
     * @throws InvalidArgumentException
     */
    public static function fromDecimalString(string $value, string $currency = 'TRY'): self
    {
        $value = trim($value);

        if (preg_match('/^(-)?(\d+)(?:[.,](\d{1,2}))?$/', $value, $matches) !== 1) {
            throw new InvalidArgumentException("Invalid money amount [{$value}].");
        }

        $whole = ltrim($matches[2], '0');

        if (strlen($whole) > self::MAX_WHOLE_DIGITS) {
            throw new InvalidArgumentException("Money amount [{$value}] is too large.");
        }

        $fraction = str_pad($matches[3] ?? '', 2, '0');
        $minor = ((int) $whole) * 100 + (int) $fraction;

        return new self(($matches[1] ?? '') === '-' ? -$minor : $minor, self::normalizeCurrency($currency));
    }

    public static function isValidDecimalString(string $value): bool
    {
        try {
            self::fromDecimalString($value);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Machine-readable decimal form for inputs, e.g. "85.50".
     */
    public function toDecimalString(): string
    {
        $absolute = abs($this->minor);

        return ($this->minor < 0 ? '-' : '').intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Localised display string, e.g. "₺85,00" (tr) or "TRY 85.00" style (en).
     * The float is used for display only, never for storage or arithmetic.
     */
    public function format(?string $locale = null): string
    {
        $formatter = new NumberFormatter($locale ?? app()->getLocale(), NumberFormatter::CURRENCY);

        return (string) $formatter->formatCurrency($this->minor / 100, $this->currency);
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor && $this->currency === $other->currency;
    }

    private static function normalizeCurrency(string $currency): string
    {
        $currency = strtoupper($currency);

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException("Invalid currency [{$currency}].");
        }

        return $currency;
    }
}
