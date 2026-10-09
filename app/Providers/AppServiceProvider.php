<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Committee;
use App\Models\ContentBlock;
use App\Models\CouncilTerm;
use App\Models\Event;
use App\Models\Location;
use App\Models\Media;
use App\Models\Service;
use App\Models\ServiceAlias;
use App\Models\ServiceFee;
use App\Models\Tag;
use App\Models\User;
use App\Services\Content\EditorialDetails;
use App\Services\Content\ReferenceProtection;
use App\Services\Search\SearchIndexer;
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
        $this->configureSearch();
        $this->configureSecurity();
        $this->configureRateLimiting();
    }

    private function configureSearch(): void
    {
        foreach (SearchIndexer::TYPES as $class) {
            $class::saved(fn (Model $model) => app(SearchIndexer::class)->sync($model));
            $class::deleted(fn (Model $model) => app(SearchIndexer::class)->remove($model));
            $class::restored(fn (Model $model) => app(SearchIndexer::class)->sync($model));
        }
        foreach ([ServiceAlias::class] as $class) {
            $sync = function (Model $alias): void {
                $service = Service::query()->whereKey($alias->getAttribute('service_id'))->first();
                if ($service) {
                    app(SearchIndexer::class)->sync($service);
                }
            };
            $class::saved($sync);
            $class::deleted($sync);
        }
        foreach ([ContentBlock::class => [null, null], ServiceFee::class => [Service::class, 'service_id']] as $child => [$parent, $foreignKey]) {
            $sync = function (Model $row) use ($parent, $foreignKey): void {
                $owner = $parent === null ? $row->getRelationValue('owner') : $parent::query()->find($row->getAttribute($foreignKey));
                if ($owner instanceof Model) {
                    app(SearchIndexer::class)->sync($owner);
                }
            };
            $child::saved($sync);
            $child::deleted($sync);
        }
        foreach ([Category::class, Tag::class] as $class) {
            $class::saved(fn () => app(SearchIndexer::class)->rebuild());
            $class::deleted(fn () => app(SearchIndexer::class)->rebuild());
        }
    }

    private function configureModels(): void
    {
        Date::use(CarbonImmutable::class);
        foreach ([Media::class, Service::class, Event::class, Location::class, CouncilTerm::class, Committee::class] as $class) {
            $class::saving(fn (Model $model) => app(EditorialDetails::class)->validate($model));
        }
        foreach (array_unique(ReferenceProtection::REFERENCES) as $class) {
            $class::forceDeleting(fn (Model $model) => app(ReferenceProtection::class)->guard($model));
        }

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
        RateLimiter::for('contact', fn (Request $request) => Limit::perHour(max(1, (int) config('contact.hourly_limit')))->by(hash_hmac('sha256', (string) $request->ip().'|'.now()->format('Y-m-d'), (string) config('app.key'))));

        RateLimiter::for('admin-password-reset', fn (Request $request) => Limit::perMinute(5)
            ->by(hash('sha256', (string) $request->ip())));
    }
}
