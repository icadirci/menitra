<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A signed-in user tried to accept an invitation sent to another email
 * address. No membership is created.
 */
class InvitationEmailMismatchException extends RuntimeException {}
