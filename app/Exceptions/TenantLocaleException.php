<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A language change that breaks a TenantLocaleService rule. The message is
 * translated and safe to show to the user.
 */
class TenantLocaleException extends RuntimeException
{
    public static function unsupported(string $locale): self
    {
        return new self(__('tenancy.locales.errors.unsupported', ['locale' => $locale]));
    }

    public static function alreadyEnabled(string $locale): self
    {
        return new self(__('tenancy.locales.errors.already_enabled', ['locale' => $locale]));
    }

    public static function notEnabled(string $locale): self
    {
        return new self(__('tenancy.locales.errors.not_enabled', ['locale' => $locale]));
    }

    public static function cannotDisableDefault(): self
    {
        return new self(__('tenancy.locales.errors.cannot_disable_default'));
    }

    public static function invalidOrder(): self
    {
        return new self(__('tenancy.locales.errors.invalid_order'));
    }
}
