<?php

namespace App\Services\Seo;

use App\Contracts\Routable;
use App\Models\Article;
use App\Models\Document;
use App\Models\Gallery;
use App\Models\Media;
use App\Services\Settings\SiteConfiguration;
use App\Support\Content\SeoData;
use App\Support\Content\SeoUrl;
use App\Support\Routing\PublicSections;
use App\Support\SearchEngineIndexing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class SeoMetadata
{
    public function __construct(private readonly SiteConfiguration $settings) {}

    public function forModel(?Model $model, ?string $pagePath = null): SeoData
    {
        $a = $model?->getAttributes() ?? [];
        $settings = $this->settings->current();
        $name = $this->settings->name();
        $image = $this->defaultImage();
        if ($model instanceof Routable && ! $model->isPubliclyReachable()) {
            return new SeoData(null, null, null, 'noindex, nofollow', $name, $image);
        }
        $title = $this->text($a['seo_title'] ?? null, false) ?? ($model instanceof Routable ? $model->displayTitle() : null);
        $home = $model === null && ($pagePath === null || $pagePath === '/');
        if ($home) {
            $title = $this->text($settings?->getAttribute('default_seo_title'), false) ?? $name;
        }
        $section = $model === null && $pagePath !== null ? PublicSections::resolve($pagePath) : null;
        $sectionDescription = $section !== null ? config('seo.sections.'.$section['kind']) : null;
        $description = $this->text($a['meta_description'] ?? null, false)
            ?? $this->text($a['summary'] ?? null)
            ?? $this->text($a['description'] ?? $a['body'] ?? null)
            ?? $this->text($sectionDescription)
            ?? $this->text($model instanceof Routable ? 'Informationen zu „'.$model->displayTitle().'“ auf der Website der '.$name.'.' : null, false)
            ?? $this->text($settings?->getAttribute('default_meta_description'), false)
            ?? (string) config('seo.default_description');
        $path = $model instanceof Document ? $model->downloadPath() : ($model instanceof Routable ? $model->publicPath() : ($model === null ? ($pagePath ?? '/') : null));
        $robots = ! empty($a['seo_noindex']) ? 'noindex, follow' : SearchEngineIndexing::robotsDirective();
        if (! SearchEngineIndexing::allowed()) {
            $robots = SearchEngineIndexing::robotsDirective();
        }

        return new SeoData($title, $description, $path !== null ? SeoUrl::path($path) : null, $robots, $name, $this->image($model) ?? $image, $model instanceof Article ? 'article' : 'website');
    }

    private function text(mixed $value, bool $markdown = true): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        $plain = $markdown ? html_entity_decode(strip_tags(Str::markdown($value, ['html_input' => 'strip', 'allow_unsafe_links' => false])), ENT_QUOTES | ENT_HTML5, 'UTF-8') : $value;

        $text = trim((string) preg_replace('/\s+/u', ' ', $plain));

        return $text !== '' ? Str::limit($text, 180, '…') : null;
    }

    /** @return array{url:string,width:int,height:int,type:string,alt:string} */
    private function defaultImage(): array
    {
        return ['url' => SeoUrl::path((string) config('seo.share_image')), 'width' => 1200, 'height' => 630, 'type' => 'image/png', 'alt' => 'Gemeinde Merching – Wappen und Gemeindename'];
    }

    /** @return array{url:string,width:int,height:int,type:string,alt:string}|null */
    private function image(?Model $model): ?array
    {
        $candidates = $model instanceof Gallery ? $model->items()->with('media')->get()->pluck('media')
            : ($model !== null && method_exists($model, 'media') ? $model->media()->get() : collect());
        foreach ($candidates as $media) {
            if (! $media instanceof Media || ! $media->isPubliclyReachable() || ! $media->hasAccessibleAlternative()
                || ! in_array($media->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)
                || ! $media->width || ! $media->height || ! Storage::disk((string) config('uploads.disk'))->exists($media->file_path)) {
                continue;
            }

            return ['url' => SeoUrl::path('/medien/'.$media->getKey()), 'width' => $media->width, 'height' => $media->height, 'type' => $media->mime_type, 'alt' => $media->alt_text ?: $media->title];
        }

        return null;
    }

    public function indexable(Model $model): bool
    {
        return empty($model->getAttributes()['seo_noindex']);
    }
}
