<?php

namespace App\Services\Routing;

use App\Contracts\Routable;
use App\Exceptions\DomainRuleViolation;
use App\Models\PublicRoute;
use App\Models\Redirect;
use App\Services\Audit\AuditLogger;
use App\Support\Routing\PublicPath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Assigns public URL paths to records and resolves incoming paths.
 *
 * - Every routable record has at most one canonical route. Its row is stable:
 *   when the path changes, the row keeps its id (navigation items reference
 *   it) and the old path is kept as a non-canonical route that redirects to
 *   the new one – old URLs never break and never form redirect chains.
 * - Paths are unique across all records and never collide with active
 *   redirects or application routes.
 * - Resolution order: content routes first, then redirects.
 */
class RouteManager
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  Model&Routable  $model  (saved)
     *
     * @throws DomainRuleViolation
     */
    public function assign(Model $model, string $rawPath): PublicRoute
    {
        ['path' => $path, 'key' => $key] = $this->validate($model, $rawPath);

        return DB::transaction(function () use ($model, $path, $key) {
            /** @var PublicRoute|null $canonical */
            $canonical = $model->canonicalRoute()->first();

            if ($canonical !== null && $canonical->path_key === $key) {
                if ($canonical->path !== $path) {
                    $canonical->update(['path' => $path]);
                }

                return $canonical;
            }

            // A former path of this record becomes canonical again.
            $model->publicRoutes()->where('path_key', $key)->delete();

            $old = $canonical?->path;

            if ($canonical !== null) {
                $oldKey = $canonical->path_key;
                $canonical->update(['path' => $path, 'path_key' => $key]);
                $model->publicRoutes()->create([
                    'path' => $old,
                    'path_key' => $oldKey,
                    'is_canonical' => false,
                    'canonical_for' => null,
                    'is_active' => true,
                ]);
            } else {
                $canonical = $model->publicRoutes()->create([
                    'path' => $path,
                    'path_key' => $key,
                    'is_canonical' => true,
                    'canonical_for' => $model->getMorphClass().':'.$model->getKey(),
                    'is_active' => true,
                ]);
            }

            $this->audit->record('route.changed', $model, ['from' => $old, 'to' => $path]);

            return $canonical;
        });
    }

    /**
     * @param  Model&Routable  $model
     * @return array{path: string, key: string}
     *
     * @throws DomainRuleViolation
     */
    public function validate(Model $model, string $rawPath): array
    {
        try {
            $normalized = PublicPath::normalize($rawPath);
        } catch (InvalidArgumentException $e) {
            throw new DomainRuleViolation($e->getMessage(), 'public_path');
        }

        if (PublicPath::isReserved($normalized['key'])) {
            throw new DomainRuleViolation('Diese Adresse ist für das System reserviert.', 'public_path');
        }

        $existing = PublicRoute::query()->where('path_key', $normalized['key'])->first();
        if ($existing !== null && ! ($existing->routable_type === $model->getMorphClass() && $existing->routable_id === $model->getKey())) {
            throw new DomainRuleViolation('Diese Adresse wird bereits von einem anderen Inhalt verwendet.', 'public_path');
        }

        if (Redirect::query()->where('source_key', $normalized['key'])->where('is_active', true)->exists()) {
            throw new DomainRuleViolation('Für diese Adresse besteht eine aktive Weiterleitung. Bitte entfernen Sie zuerst die Weiterleitung.', 'public_path');
        }

        return $normalized;
    }

    /**
     * Unused default path from the type prefix and the title, e.g. "/aktuelles/neues-feuerwehrhaus".
     *
     * @param  Model&Routable  $model
     */
    public function suggestPath(Model $model): string
    {
        $slug = Str::slug($model->displayTitle(), '-', 'de') ?: 'eintrag';
        $base = rtrim($model::defaultPathPrefix(), '/').'/'.Str::limit($slug, 120, '');
        $candidate = $base;

        for ($i = 2; $this->isTaken($candidate, $model); $i++) {
            $candidate = $base.'-'.$i;
        }

        return $candidate;
    }

    /**
     * @param  Model&Routable  $model
     */
    private function isTaken(string $path, Model $model): bool
    {
        $key = PublicPath::key($path);

        return PublicPath::isReserved($key)
            || PublicRoute::query()->where('path_key', $key)
                ->where(fn ($q) => $q->where('routable_type', '!=', $model->getMorphClass())->orWhere('routable_id', '!=', $model->getKey() ?? 0))
                ->exists()
            || Redirect::query()->where('source_key', $key)->exists();
    }

    /**
     * Final target of a non-canonical request path, so that host/HTTPS
     * normalisation, slash/case variants, former content paths and legacy
     * redirects all resolve in ONE redirect hop.
     *
     * @return array{location: string, status: int} location = path (with leading "/") or absolute URL
     */
    public function finalLocation(string $requestPath): array
    {
        $target = $this->resolve($requestPath);

        if ($target instanceof Redirect && $target->status_code !== 410 && $target->destination !== null) {
            return ['location' => $target->destination, 'status' => $target->status_code === 302 ? 302 : 301];
        }

        if ($target instanceof PublicRoute) {
            $canonical = $target->is_canonical ? $target : PublicRoute::query()
                ->where('routable_type', $target->routable_type)->where('routable_id', $target->routable_id)
                ->where('is_canonical', true)->where('is_active', true)->first();

            if ($canonical !== null) {
                return ['location' => $canonical->path, 'status' => 301];
            }
        }

        return ['location' => PublicPath::withoutTrailingSlash(rawurldecode($requestPath)), 'status' => 301];
    }

    /**
     * Resolve an incoming (raw) request path. Content routes take precedence
     * over redirects.
     */
    public function resolve(string $requestPath): PublicRoute|Redirect|null
    {
        $key = PublicPath::keyForRequest($requestPath);

        return PublicRoute::query()->where('path_key', $key)->where('is_active', true)->first()
            ?? Redirect::query()->where('source_key', $key)->where('is_active', true)->first();
    }
}
