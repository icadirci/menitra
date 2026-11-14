<?php

declare(strict_types=1);

namespace App\Exceptions;

use LogicException;

/**
 * Thrown when code tries to write a tenant-owned row for a tenant other than
 * the current context, or to move a row to another tenant.
 */
class CrossTenantWriteException extends LogicException {}
