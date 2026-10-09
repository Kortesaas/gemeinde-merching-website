<?php

namespace App\Services\Seo;

use App\Contracts\Routable;
use App\Models\Document;
use App\Models\PublicRoute;
use App\Services\Content\DocumentStorage;
use App\Services\Routing\RouteManager;
use App\Support\Content\SeoUrl;

class Sitemap
{
    public const PAGE_SIZE = 10000;

    public function __construct(private readonly SeoMetadata $seo) {}

    /** @return list<array{url:string,modified:string|null}> */
    public function entries(): array
    {
        $entries = [['url' => SeoUrl::path('/'), 'modified' => null]];
        foreach (PublicRoute::query()->where('is_canonical', true)->where('is_active', true)->orderBy('id')->cursor() as $route) {
            $model = $route->routable;
            if (! $model instanceof Routable || ! $model->isPubliclyReachable() || ! $this->seo->indexable($model)) {
                continue;
            }
            if ($model instanceof Document && ! app(DocumentStorage::class)->exists($model)) {
                continue;
            }
            $entries[] = ['url' => SeoUrl::path($route->path), 'modified' => $model->getAttribute('updated_at')?->toAtomString()];
        }
        foreach (Document::query()->orderBy('id')->cursor() as $document) {
            if ($document->publicPath() !== null || ! $document->isPubliclyReachable() || ! $this->seo->indexable($document) || ! app(DocumentStorage::class)->exists($document)) {
                continue;
            }
            $entries[] = ['url' => SeoUrl::path($document->downloadPath()), 'modified' => $document->updated_at?->toAtomString()];
        }
        // Fallback listing pages are real public destinations too. A managed
        // private page or redirect must never be reintroduced through this list.
        foreach (array_keys((array) config('public.sections', [])) as $path) {
            if (app(RouteManager::class)->resolve($path) === null) {
                $entries[] = ['url' => SeoUrl::path($path), 'modified' => null];
            }
        }

        return $entries;
    }
}
