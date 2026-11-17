<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * The provider couldn't be reached, refused a request or answered with an
 * invalid signature. The message is technical (logged / Sentry); customers
 * see a translated generic message.
 */
class PaymentGatewayException extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $providerCode = null)
    {
        parent::__construct($message);
    }
}
