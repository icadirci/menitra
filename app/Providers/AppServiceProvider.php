<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\RestrictLivewireFileEndpoints;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Features\SupportFileUploads\FilePreviewController;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->restrictLivewireToTheCentralDomain();
    }

    /**
     * Livewire is used only by the Filament panels (central domain).
     *
     * - Update: a custom route bound to the central domain. Once it exists,
     *   Livewire rejects requests through its default (host-agnostic) update
     *   route with 404. Panel authorization runs on every update through
     *   Filament's Livewire-persistent middleware.
     * - Upload / preview: RestrictLivewireFileEndpoints (central host + panel
     *   user), in addition to Livewire's signed URLs and throttling.
     */
    private function restrictLivewireToTheCentralDomain(): void
    {
        Livewire::setUpdateRoute(fn (array $handle, string $path) => Route::post(
            str_replace('/update', '/panels/update', $path),
            $handle,
        )
            ->domain(config('tenancy.central_domain'))
            ->middleware('web')
            ->name('panels.livewire.update'));

        config(['livewire.temporary_file_upload.middleware' => ['throttle:60,1', RestrictLivewireFileEndpoints::class]]);

        if (! in_array(RestrictLivewireFileEndpoints::class, FilePreviewController::$middleware, true)) {
            FilePreviewController::$middleware[] = RestrictLivewireFileEndpoints::class;
        }
    }
}
