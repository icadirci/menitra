<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Http\Middleware\RestrictLivewireFileEndpoints;
use App\Services\Billing\Iyzico\IyzicoGateway;
use App\Services\Marketing\MarketingContent;
use App\Support\ErrorPage;
use App\Support\MarketingUrl;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\FilePreviewController;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Memoizes platform content (e.g. contact details) within one request.
        $this->app->scoped(MarketingContent::class);

        // Payment provider (config/billing.php, credentials in config/services.php).
        $this->app->singleton(PaymentGateway::class, fn (): PaymentGateway => match (config('billing.gateway')) {
            'iyzico' => new IyzicoGateway(
                apiKey: (string) config('services.iyzico.api_key'),
                secretKey: (string) config('services.iyzico.secret_key'),
                baseUrl: (string) config('services.iyzico.base_url'),
            ),
            default => throw new InvalidArgumentException('Unknown payment gateway ['.config('billing.gateway').'].'),
        });
    }

    public function boot(): void
    {
        $this->restrictLivewireToTheCentralDomain();
        $this->configureMarketing();
        $this->configureErrorPages();
    }

    /**
     * HTTP error views (errors::403 … errors::503): marketing design on the
     * central domain, plain platform page on menu hosts (App\Support\ErrorPage).
     */
    private function configureErrorPages(): void
    {
        View::composer('errors::*', function ($view): void {
            $request = request();
            ErrorPage::applyLocale($request);
            $view->with('menuHost', ErrorPage::isMenuHost($request));
        });
    }

    /**
     * - Footer: platform contact details from the /admin site settings.
     * - Contact form: per IP, 3 messages a minute and 10 an hour. Over the
     *   limit the visitor is sent back to the form with an error instead of a
     *   bare 429 page.
     */
    private function configureMarketing(): void
    {
        View::composer('components.marketing.footer', function ($view): void {
            $view->with('contact', app(MarketingContent::class)->contactDetails());
        });

        // Public QR redirects (/q/{code}): generous per-IP limit against enumeration.
        RateLimiter::for('qr', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        // Public menu pages: per-IP ceiling against slug/branch enumeration
        // (unknown slugs are not cached, so each probe costs a query).
        RateLimiter::for('menu', fn (Request $request) => Limit::perMinute((int) config('tenancy.menu_rate_limit'))->by('menu|'.$request->ip()));

        // Payment provider callback / webhook (routes/billing.php).
        RateLimiter::for('billing', fn (Request $request) => Limit::perMinute(60)->by('billing|'.$request->ip()));

        RateLimiter::for('contact', function (Request $request) {
            $back = fn () => redirect()
                ->to(MarketingUrl::to('contact', fragment: 'contact-form'))
                ->withInput($request->except(['_token']))
                ->withErrors(['message' => __('marketing.contact_page.throttled')]);

            return [
                Limit::perMinute(3)->by('contact-minute|'.$request->ip())->response($back),
                Limit::perHour(10)->by('contact-hour|'.$request->ip())->response($back),
            ];
        });
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
