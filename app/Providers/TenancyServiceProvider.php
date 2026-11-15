<?php

declare(strict_types=1);

namespace App\Providers;

use App\Observers\MenuCacheObserver;
use App\Services\Dashboard\BusinessDashboard;
use App\Services\Subscription\FeatureGate;
use App\Tenancy\Queue\TenantQueueListener;
use App\Tenancy\TenantContext;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped: a fresh, empty context for every request and queued job.
        $this->app->scoped(TenantContext::class);

        $this->app->singleton(TenantQueueListener::class);

        // Scoped like the context it depends on: limits are memoised per request/job.
        $this->app->scoped(FeatureGate::class);

        // Dashboard read model, memoised per request like FeatureGate.
        $this->app->scoped(BusinessDashboard::class);
    }

    public function boot(): void
    {
        // Public menu cache invalidation (docs/architecture/caching.md).
        foreach (MenuCacheObserver::observed() as $model) {
            $model::observe(MenuCacheObserver::class);
        }

        Queue::createPayloadUsing(fn (): array => TenantQueueListener::payloadFor($this->app->make(TenantContext::class)));

        Event::listen(JobProcessing::class, [TenantQueueListener::class, 'restore']);
        Event::listen(
            [JobProcessed::class, JobFailed::class, JobExceptionOccurred::class, JobAttempted::class],
            [TenantQueueListener::class, 'release'],
        );
    }
}
