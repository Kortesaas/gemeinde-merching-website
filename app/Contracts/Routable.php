<?php

namespace App\Contracts;

use App\Models\PublicRoute;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * A record that can be reached under a public URL path (PublicRoute),
 * implemented via App\Models\Concerns\HasPublicRoute.
 */
interface Routable
{
    public function displayTitle(): string;

    /** May an anonymous visitor see this record right now? */
    public function isPubliclyReachable(): bool;

    /** Suggested path prefix for new records, e.g. "/aktuelles". */
    public static function defaultPathPrefix(): string;

    /**
     * @return MorphMany<PublicRoute, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function publicRoutes(): MorphMany;

    /**
     * @return MorphOne<PublicRoute, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function canonicalRoute(): MorphOne;

    public function publicPath(): ?string;
}
