<?php

namespace App\Services\Seo;

use App\Contracts\Routable;
use App\Models\Document;
use App\Services\Settings\SiteConfiguration;
use App\Support\Content\SeoData;
use App\Support\Routing\PublicPath;
use App\Support\SearchEngineIndexing;
use Illuminate\Database\Eloquent\Model;

final class SeoMetadata
{
    public function __construct(private readonly SiteConfiguration $settings) {}

    public function forModel(?Model $model, ?string $pagePath = null): SeoData
    {
        $a = $model?->getAttributes() ?? [];
        $settings = $this->settings->current();
        $title = $a['seo_title'] ?? ($model === null && ($pagePath === null || $pagePath === '/') ? $settings?->getAttribute('default_seo_title') : null);
        $description = $a['meta_description'] ?? $a['summary'] ?? $settings?->getAttribute('default_meta_description');
        $path = $model instanceof Document ? $model->downloadPath() : ($model instanceof Routable ? $model->publicPath() : ($model === null ? ($pagePath ?? '/') : null));
        $robots = ! empty($a['seo_noindex']) ? 'noindex, follow' : SearchEngineIndexing::robotsDirective();
        if (! SearchEngineIndexing::allowed()) {
            $robots = SearchEngineIndexing::robotsDirective();
        }

        return new SeoData($title, $description, $path !== null ? PublicPath::absoluteUrl($path) : null, $robots, $this->settings->name());
    }

    public function indexable(Model $model): bool
    {
        return empty($model->getAttributes()['seo_noindex']);
    }
}
