<?php

namespace App\Support\Content;

use App\Models;
use App\Services\Content\PublicCatalog;
use App\Support\Routing\PublicPath;
use App\Support\Routing\PublicSections;
use Illuminate\Database\Eloquent\Model;

/** Presentation helpers for public templates; no data is invented or cached here. */
final class PublicFormat
{
    /** Listing section that contains records of a type, used for fallback breadcrumbs. */
    private const SECTIONS = [
        Models\BudgetPlan::class => 'budgets', Models\Service::class => 'services', Models\LifeSituation::class => 'services', Models\Article::class => 'articles',
        Models\Event::class => 'events', Models\PublicNotice::class => 'notices', Models\Department::class => 'directory',
        Models\Organization::class => 'organizations', Models\Location::class => 'directory', Models\CouncilTerm::class => 'directory',
    ];

    /**
     * Plain-text field split into non-empty lines (editors enter one item per line).
     *
     * @return list<string>
     */
    public static function lines(?string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $text) ?: []), fn ($line) => $line !== ''));
    }

    public static function fileSize(?int $bytes): string
    {
        $bytes = max(0, (int) $bytes);
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / 1024 / 1024, 1, ',', '.').' MB';
        }

        return number_format(max(1, (int) ceil($bytes / 1024)), 0, ',', '.').' KB';
    }

    public static function phoneHref(string $phone): string
    {
        return 'tel:'.preg_replace('/[^+0-9]/', '', $phone);
    }

    public static function money(float|string|null $amount): ?string
    {
        return $amount === null ? null : number_format((float) $amount, 2, ',', '.').' €';
    }

    /** @return array{label:string,url:string}|null */
    public static function section(Model $model): ?array
    {
        $kind = self::SECTIONS[$model::class] ?? null;
        if ($kind === null) {
            return null;
        }
        $path = app(PublicCatalog::class)->sectionPath($kind);
        $section = PublicSections::resolve($path);
        $route = Models\PublicRoute::query()->where('path_key', PublicPath::key($path))->where('is_canonical', true)->where('is_active', true)->first();
        $page = $route?->routable()->first();
        $label = $page instanceof Models\Page && $page->isPubliclyReachable() ? $page->title : ($section['title'] ?? null);

        return $label === null ? null : ['label' => $label, 'url' => PublicPath::toUrl($path)];
    }

    /**
     * Escaped text with search terms wrapped in <mark>; safe for {!! !!}.
     *
     * @param  list<string>  $terms
     */
    public static function highlight(string $text, array $terms): string
    {
        $escaped = e($text);
        $terms = array_values(array_filter(array_map(fn ($t) => preg_quote(e(trim($t)), '/'), $terms), fn ($t) => mb_strlen($t) >= 2));
        if ($terms === []) {
            return $escaped;
        }

        return (string) preg_replace('/('.implode('|', $terms).')/iu', '<mark>$1</mark>', $escaped);
    }

    /** First line of an opening-hours text, for compact summaries. */
    public static function firstLine(?string $text): ?string
    {
        return self::lines($text)[0] ?? null;
    }
}
