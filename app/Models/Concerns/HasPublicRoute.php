<?php

namespace App\Models\Concerns;

use App\Models\PublicRoute;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Public URL(s) of a record. The URL path is stored in public_routes and is
 * independent of IDs, slugs and navigation (see RouteManager).
 */
trait HasPublicRoute
{
    public static function bootHasPublicRoute(): void
    {
        static::forceDeleted(function ($model) {
            $model->publicRoutes()->delete();
        });
    }

    /**
     * @return MorphMany<PublicRoute, $this>
     */
    public function publicRoutes(): MorphMany
    {
        return $this->morphMany(PublicRoute::class, 'routable');
    }

    /**
     * @return MorphOne<PublicRoute, $this>
     */
    public function canonicalRoute(): MorphOne
    {
        return $this->morphOne(PublicRoute::class, 'routable')->where('is_canonical', true);
    }

    public function publicPath(): ?string
    {
        $route = $this->canonicalRoute;

        return $route !== null && $route->is_active ? $route->path : null;
    }

    /**
     * Default: new records of this type get a suggested route. Types with
     * opt-in public pages override this.
     */
    public static function createsRouteAutomatically(): bool
    {
        return true;
    }
}
