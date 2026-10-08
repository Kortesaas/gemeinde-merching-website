<?php

use App\Http\Controllers\Admin\Account\TwoFactorSetupController;
use App\Http\Controllers\Admin\Auth\ForgotPasswordController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\Auth\ResetPasswordController;
use App\Http\Controllers\Admin\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Admin\DashboardController;
use App\Support\Authorization\Permission;
use Illuminate\Support\Facades\Route;

/*
| Employee backend routes.
|
| Mounted under config('admin.path') (default "/verwaltung"), route names are
| prefixed with "admin.", middleware group "web" (session + CSRF). The prefix
| is not a security measure – see the middleware below.
*/

// --- Authentication & recovery (only for guests) ----------------------------
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');

    Route::get('login/zwei-faktor', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
    Route::post('login/zwei-faktor', [TwoFactorChallengeController::class, 'store'])->name('two-factor.challenge.store');
    Route::post('login/wiederherstellungscode', [TwoFactorChallengeController::class, 'storeRecoveryCode'])
        ->name('two-factor.recovery.store');

    Route::get('passwort-vergessen', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('passwort-vergessen', [ForgotPasswordController::class, 'store'])
        ->middleware('throttle:admin-password-reset')
        ->name('password.email');

    Route::get('passwort-zuruecksetzen/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('passwort-zuruecksetzen', [ResetPasswordController::class, 'store'])
        ->middleware('throttle:admin-password-reset')
        ->name('password.update');
});

// --- Authenticated area -------------------------------------------------------
Route::middleware(['auth', 'auth.session', 'admin.session'])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    // MFA enrolment must be reachable before MFA is set up.
    Route::get('konto/zwei-faktor', [TwoFactorSetupController::class, 'show'])->name('two-factor.setup');
    Route::post('konto/zwei-faktor', [TwoFactorSetupController::class, 'confirm'])
        ->middleware('throttle:10,1')
        ->name('two-factor.confirm');

    // Everything else requires MFA (per policy) and backend access permission.
    Route::middleware(['admin.mfa', 'can:'.Permission::AccessAdmin->value])->group(function () {
        Route::redirect('/', '/'.config('admin.path').'/dashboard')->name('home');
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::post('konto/zwei-faktor/wiederherstellungscodes', [TwoFactorSetupController::class, 'regenerateRecoveryCodes'])
            ->middleware('throttle:6,1')
            ->name('two-factor.recovery-codes');
    });
});
