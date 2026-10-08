<?php

use App\Http\Middleware\AdminAreaHeaders;
use App\Http\Middleware\CanonicalUrlRedirect;
use App\Http\Middleware\PreventRequestForgery;
use App\Http\Middleware\RequireTwoFactor;
use App\Http\Middleware\SearchEngineIndexingHeader;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\ValidateAdminSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as BasePreventRequestForgery;
use Illuminate\Http\Middleware\TrustProxies as BaseTrustProxies;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        using: function () {
            // Backend first, so a future public catch-all route (slugs, legacy
            // URL redirects) can never shadow it.
            Route::middleware('web')
                ->prefix(config('admin.path'))
                ->name('admin.')
                ->group(base_path('routes/admin.php'));

            // Public website: stateless, cookie-free.
            Route::middleware('public')
                ->group(base_path('routes/public.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global middleware (every request, including 404s).
        $middleware->replace(BaseTrustProxies::class, TrustProxies::class);
        $middleware->trustHosts(
            at: fn () => array_map(
                fn (string $host) => '^'.preg_quote($host).'$',
                [...(array) config('security.trusted_hosts'), ...(array) config('security.redirect_hosts')],
            ),
            subdomains: false,
        );
        $middleware->append([
            SecurityHeaders::class,
            AdminAreaHeaders::class,
            SearchEngineIndexingHeader::class,
            CanonicalUrlRedirect::class,
        ]);

        // Stateless group for the public website: no session, no cookies.
        $middleware->group('public', [
            SubstituteBindings::class,
        ]);

        // Stateful group (backend, later: public forms). CSRF without the
        // XSRF-TOKEN cookie.
        $middleware->web(replace: [
            BasePreventRequestForgery::class => PreventRequestForgery::class,
        ]);

        $middleware->alias([
            'admin.session' => ValidateAdminSession::class,
            'admin.mfa' => RequireTwoFactor::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Never flash secrets back into the session after validation errors.
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
            'code',
            'recovery_code',
            'token',
        ]);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson(),
        );
    })->create();
