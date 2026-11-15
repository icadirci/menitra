<?php

declare(strict_types=1);

namespace App\Filament\App\Forms;

use App\Support\Money;
use App\Tenancy\TenantContext;
use Closure;
use Filament\Forms\Components\TextInput;

/**
 * Money field: the user types TL ("85,50"), the record stores kuruş (8550).
 * Parsing goes through App\Support\Money — never floats. Negative amounts
 * are rejected unless allowNegative() is called (e.g. extra price deltas).
 */
class MoneyInput extends TextInput
{
    protected bool $allowsNegative = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inputMode('decimal');
        $this->prefix(fn (): string => app(TenantContext::class)->check()
            ? app(TenantContext::class)->tenant()->currency
            : config('subscriptions.currency'));
        $this->formatStateUsing(fn (mixed $state): ?string => is_int($state) || (is_string($state) && ctype_digit($state))
            ? Money::fromMinor((int) $state)->toDecimalString()
            : $state);
        $this->dehydrateStateUsing(fn (mixed $state): ?int => blank($state)
            ? null
            : Money::fromDecimalString((string) $state)->minor);
        $this->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
            if (blank($value)) {
                return;
            }

            if (! is_string($value) && ! is_int($value)) {
                $fail(__('panels.app.money.invalid'));

                return;
            }

            if (! Money::isValidDecimalString((string) $value)) {
                $fail(__('panels.app.money.invalid'));

                return;
            }

            if (! $this->allowsNegative && Money::fromDecimalString((string) $value)->isNegative()) {
                $fail(__('panels.app.money.negative'));
            }
        });
    }

    public function allowNegative(bool $condition = true): static
    {
        $this->allowsNegative = $condition;

        return $this;
    }
}
