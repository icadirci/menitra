<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A checkout can't be started (e.g. no billing profile, renewal window not
 * open, plan change during a paid period). The message is translated and
 * shown to the owner.
 */
class CheckoutNotAllowedException extends RuntimeException
{
    public static function because(string $reason, array $replace = []): self
    {
        return new self(__("billing.errors.{$reason}", $replace));
    }
}
