<?php

namespace App\Providers;

use App\Models\User;
use App\Session\PrivacyDatabaseSessionHandler;
use App\Support\MorphMap;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->isProduction()) {
            // Hard guarantee: debug output (stack traces, SQL, paths, config)
            // is never shown in production, even if APP_DEBUG is set by mistake.
            config(['app.debug' => false]);

            // Production cookies are always Secure and HttpOnly.
            config(['session.secure' => true, 'session.http_only' => true]);
        }

        if (! $this->app->isLocal()) {
            // Keep function arguments (e.g. passwords) out of exception traces.
            ini_set('zend.exception_ignore_args', '1');
        }
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureSecurity();
        $this->configureRateLimiting();
    }

    private function configureModels(): void
    {
        Date::use(CarbonImmutable::class);

        // Fail loudly during development on lazy loading, silently discarded
        // attributes and access to missing attributes.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Stable aliases instead of PHP class names in polymorphic columns.
        Relation::enforceMorphMap(MorphMap::MAP);
    }

    private function configureSecurity(): void
    {
        // Refuse destructive commands (migrate:fresh, db:wipe, …) in production.
        DB::prohibitDestructiveCommands($this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
            URL::forceRootUrl((string) config('app.url'));
        }

        // Password policy: length over complexity rules (BSI / NIST guidance).
        // Max 64 characters keeps passwords within bcrypt's 72-byte input limit
        // for typical input.
        Password::defaults(fn () => Password::min(12)->max(64));

        // Server-side sessions without IP address / user agent.
        Session::extend('database', fn ($app) => new PrivacyDatabaseSessionHandler(
            $app['db']->connection($app['config']->get('session.connection')),
            $app['config']->get('session.table'),
            $app['config']->get('session.lifetime'),
            $app,
        ));

        ResetPassword::createUrlUsing(fn (User $user, string $token) => route('admin.password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]));
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('admin-password-reset', fn (Request $request) => Limit::perMinute(5)
            ->by(hash('sha256', (string) $request->ip())));
    }
}
