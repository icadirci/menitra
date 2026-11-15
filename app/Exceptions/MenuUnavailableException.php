<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * The tenant is suspended, its access ended, or a QR code is inactive: the
 * platform's unavailable page is rendered (503, no-store, noindex).
 */
final class MenuUnavailableException extends RuntimeException
{
    public function __construct(public readonly ?string $businessName = null)
    {
        parent::__construct('The menu is unavailable.');
    }
}
