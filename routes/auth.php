<?php

declare(strict_types=1);

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\AccountQrCodeController;
use App\Http\Controllers\Team\InvitationController;
use App\Http\Middleware\ResolveAccountTenant;
use Illuminate\Routing\RedirectController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController;
use Laravel\Fortify\Http\Controllers\EmailVerificationPromptController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;
use Laravel\Fortify\Http\Controllers\VerifyEmailController;

/*
|--------------------------------------------------------------------------
| Public authentication routes (central domain only)
|--------------------------------------------------------------------------
|
| Fortify controllers for registration, password reset and email
| verification. There is deliberately no POST /login: login belongs to the
| Filament panels (/admin/login, /app/login). POST /logout exists because the
| panel's own logout sits behind the verified-email requirement, which an
| unverified user on /email/verify can't pass.
|
*/

Route::domain(config('tenancy.central_domain'))->group(function (): void {

    // Named `login` so Laravel's auth redirects and password-reset responses
    // land on the business panel login.
    Route::get('/login', RedirectController::class)
        ->defaults('destination', '/app/login')
        ->defaults('status', 302)
        ->name('login');

    Route::middleware('guest')->group(function (): void {
        Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
        Route::post('/register', [RegisteredUserController::class, 'store'])
            ->middleware('throttle:registration')
            ->name('register.store');

        Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
        Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
            ->middleware('throttle:password-reset')
            ->name('password.email');

        Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
        Route::post('/reset-password', [NewPasswordController::class, 'store'])
            ->middleware('throttle:password-reset')
            ->name('password.update');
    });

    Route::middleware('auth')->group(function (): void {
        Route::get('/email/verify', EmailVerificationPromptController::class)->name('verification.notice');
        Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');
        Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('verification.send');

        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    });

    // Team invitations (launch plan Faz 7): reachable signed in or out. The
    // token is checked by InvitationTokenResolver, so the path isn't
    // constrained and every invalid token gets the same page.
    Route::get('/invitations/accepted', [InvitationController::class, 'accepted'])->name('invitations.accepted');
    Route::middleware('throttle:invitations')->group(function (): void {
        Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
        Route::post('/invitations/{token}', [InvitationController::class, 'accept'])->name('invitations.accept');
    });

    // Minimal read-only account page. The tenant comes only from the user's
    // verified membership (ResolveAccountTenant), never from the request.
    Route::middleware(['auth', 'verified', ResolveAccountTenant::class])->group(function (): void {
        Route::get('/account', AccountController::class)->name('account');
        Route::get('/account/qr/{code}.{format}', AccountQrCodeController::class)
            ->where(['code' => '[0-9A-Za-z]{10}', 'format' => 'svg|png'])
            ->name('account.qr');
    });
});
