<?php

declare(strict_types=1);

namespace App\Providers;

use App\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped: a fresh, empty context for every request and queued job.
        $this->app->scoped(TenantContext::class);
    }
}
