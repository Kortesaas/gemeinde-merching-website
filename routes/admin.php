<?php

use App\Admin\ResourceRegistry;
use App\Http\Controllers\Admin\Account\TwoFactorSetupController;
use App\Http\Controllers\Admin\Auth\ForgotPasswordController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\Auth\ResetPasswordController;
use App\Http\Controllers\Admin\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Admin\BudgetController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DocumentFileController;
use App\Http\Controllers\Admin\MediaFileController;
use App\Http\Controllers\Admin\OverviewController;
use App\Http\Controllers\Admin\PlacementController;
use App\Http\Controllers\Admin\ProposalController;
use App\Http\Controllers\Admin\ResourceController;
use App\Http\Controllers\Admin\RevisionController;
use App\Http\Controllers\Admin\UserController;
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
        Route::get('inhalte', OverviewController::class)->name('overview');

        Route::post('konto/zwei-faktor/wiederherstellungscodes', [TwoFactorSetupController::class, 'regenerateRecoveryCodes'])
            ->middleware('throttle:6,1')
            ->name('two-factor.recovery-codes');

        // --- Content management (functional CRUD, see App\Admin\Resource) ---
        // Authorization happens per action in policies (ContentPolicy).
        foreach (ResourceRegistry::all() as $key => $resource) {
            Route::prefix($resource->slug())->name($key.'.')->group(function () use ($key) {
                $r = fn ($route) => $route->defaults('resource', $key)->whereNumber(['record', 'revision', 'pivot']);

                $r(Route::get('/', [ResourceController::class, 'index'])->name('index'));
                $r(Route::get('neu', [ResourceController::class, 'create'])->name('create'));
                $r(Route::post('/', [ResourceController::class, 'store'])->name('store'));
                $r(Route::get('{record}', [ResourceController::class, 'edit'])->name('edit'));
                $r(Route::put('{record}', [ResourceController::class, 'update'])->name('update'));
                $r(Route::delete('{record}', [ResourceController::class, 'destroy'])->name('destroy'));
                $r(Route::post('{record}/wiederherstellen', [ResourceController::class, 'restore'])->name('restore'));
                $r(Route::delete('{record}/endgueltig', [ResourceController::class, 'forceDelete'])->name('force-delete'));

                $r(Route::get('{record}/versionen', [RevisionController::class, 'index'])->name('revisions'));
                $r(Route::get('{record}/versionen/{revision}', [RevisionController::class, 'show'])->name('revisions.show'));
                $r(Route::post('{record}/versionen/{revision}/wiederherstellen', [RevisionController::class, 'restore'])->name('revisions.restore'));

                $r(Route::post('{record}/zuordnungen', [PlacementController::class, 'store'])->name('placements.store'));
                $r(Route::post('{record}/vorschlaege', [ProposalController::class, 'store'])->name('proposals.store'));
                $r(Route::patch('{record}/zuordnungen/{kind}/{pivot}', [PlacementController::class, 'update'])->name('placements.update'));
                $r(Route::delete('{record}/zuordnungen/{kind}/{pivot}', [PlacementController::class, 'destroy'])->name('placements.destroy'));
            });
        }

        // --- Change proposals & review queue -----------------------------------
        Route::prefix('freigaben')->name('proposals.')->controller(ProposalController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('{proposal}', 'show')->name('show');
            Route::put('{proposal}', 'update')->name('update');
            Route::post('{proposal}/einreichen', 'submit')->name('submit');
            Route::post('{proposal}/zurueckziehen', 'withdraw')->name('withdraw');
            Route::post('{proposal}/freigeben', 'apply')->name('apply');
            Route::post('{proposal}/ablehnen', 'reject')->name('reject');
            Route::post('{proposal}/zuordnungen', 'storePlacement')->name('placements.store');
            Route::delete('{proposal}/zuordnungen/{kind}/{index}', 'destroyPlacement')->whereNumber('index')->name('placements.destroy');
        })->whereNumber('proposal');

        Route::prefix('haushaltsplaene/{record}')->whereNumber('record')->name('budget-plan.')->controller(BudgetController::class)->group(function () {
            Route::post('pdfs', 'upload')->name('upload');
            Route::post('gesamt-pdf', 'generate')->name('generate');
            Route::get('dateien/{kind}/{file}', 'file')->where('kind', 'quelle|gesamt')->whereNumber('file')->name('file');
            Route::get('nachweis/{publication}', 'proof')->whereNumber('publication')->name('proof');
        });

        Route::get('medien/{record}/datei', MediaFileController::class)->whereNumber('record')->name('media.file');

        Route::get('dokumente/{record}/datei', DocumentFileController::class)->whereNumber('record')->name('document.file');

        Route::resource('benutzer', UserController::class)->only(['index', 'create', 'store', 'edit', 'update'])
            ->parameters(['benutzer' => 'user'])->names('user');
    });
});
