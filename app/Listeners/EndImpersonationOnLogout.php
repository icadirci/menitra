<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\Admin\Impersonation;
use Illuminate\Auth\Events\Logout;

/**
 * Logging out (e.g. the /app logout) during an impersonation ends it; the
 * end is audited before the session is invalidated.
 */
final class EndImpersonationOnLogout
{
    public function __construct(private readonly Impersonation $impersonation) {}

    public function handle(Logout $event): void
    {
        $this->impersonation->leave('logout');
    }
}
