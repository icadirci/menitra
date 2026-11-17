<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\RegisteredResponse;
use App\Models\Plan;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Login lives in the Filament panels; only the routes in
        // routes/auth.php are exposed (no Fortify POST /login or /logout).
        Fortify::ignoreRoutes();

        // "Pay now" at registration goes to the payment page (RegisteredResponse).
        $this->app->singleton(RegisterResponse::class, RegisteredResponse::class);
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // ?plan= preselects an active, public plan (pricing page CTAs); anything else is ignored.
        Fortify::registerView(function (Request $request) {
            $code = $request->query('plan');
            $plan = is_string($code) && $code !== ''
                ? Plan::query()->selectable()->where('code', $code)->with('translations')->first()
                : null;

            return view('auth.register', ['selectedPlan' => $plan]);
        });
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));

        RateLimiter::for('registration', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // /invitations/{token}: viewing is cheap; accepting (password checks,
        // account creation) is limited per IP and per token.
        RateLimiter::for('invitations', function (Request $request) {
            if (! $request->isMethod('post')) {
                return Limit::perMinute(30)->by('invitations|'.$request->ip());
            }

            return [
                Limit::perMinute(5)->by('invitations-accept|'.$request->ip()),
                Limit::perMinute(10)->by('invitations-token|'.hash('sha256', (string) $request->route('token'))),
            ];
        });

        RateLimiter::for('password-reset', function (Request $request) {
            $email = Str::transliterate(Str::lower((string) $request->input('email')));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });
    }
}
