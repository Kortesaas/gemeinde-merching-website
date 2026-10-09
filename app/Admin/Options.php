<?php

namespace App\Admin;

use App\Contracts\Routable;
use App\Enums\CategoryContext;
use App\Models\Category;
use App\Models\Department;
use App\Models\Document;
use App\Models\ExternalResource;
use App\Models\Location;
use App\Models\NavigationItem;
use App\Models\Organization;
use App\Models\Page;
use App\Models\Person;
use App\Models\PublicRoute;
use App\Models\Service;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;

/**
 * Option lists (id => label) for relation fields. Only listed IDs validate.
 */
final class Options
{
    /**
     * @return array<int, string>
     */
    public static function categories(CategoryContext $context): array
    {
        return Category::query()->for($context)->pluck('name', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    public static function tags(): array
    {
        return Tag::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    public static function departments(): array
    {
        return Department::query()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    public static function people(): array
    {
        return Person::query()->orderBy('last_name')->orderBy('first_name')->get()
            ->mapWithKeys(fn (Person $p) => [$p->id => $p->displayTitle().($p->is_active ? '' : ' (inaktiv)')])->all();
    }

    /**
     * @return array<int, string>
     */
    public static function locations(): array
    {
        return Location::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    public static function organizations(): array
    {
        return Organization::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    public static function externalResources(): array
    {
        return ExternalResource::query()->orderBy('title')->pluck('title', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    public static function documents(?Model $except = null): array
    {
        return Document::query()->when($except?->exists, fn ($q) => $q->whereKeyNot($except?->getKey()))
            ->orderBy('title')->get()
            ->mapWithKeys(fn (Document $d) => [$d->id => $d->title.' ('.strtoupper($d->extension).($d->year ? ', '.$d->year : '').')'])->all();
    }

    /**
     * @return array<int, string>
     */
    public static function services(?Model $except = null): array
    {
        return Service::query()->when($except?->exists, fn ($q) => $q->whereKeyNot($except?->getKey()))
            ->orderBy('title')->pluck('title', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    public static function pages(): array
    {
        return Page::query()->orderBy('title')->pluck('title', 'id')->all();
    }

    /**
     * Canonical content routes (navigation targets).
     *
     * @return array<int, string>
     */
    public static function canonicalRoutes(): array
    {
        return PublicRoute::query()->with('routable')->where('is_canonical', true)->orderBy('path')->get()
            ->mapWithKeys(fn (PublicRoute $r) => [$r->id => $r->path.' – '.($r->routable instanceof Routable ? $r->routable->displayTitle() : '?')])->all();
    }

    /**
     * @return array<int, string>
     */
    public static function navigationItems(?Model $except = null): array
    {
        return NavigationItem::query()->when($except?->exists, fn ($q) => $q->whereKeyNot($except?->getKey()))
            ->orderBy('menu')->orderBy('sort_order')->get()
            ->mapWithKeys(fn (NavigationItem $i) => [$i->id => $i->menu->label().' › '.$i->label])->all();
    }
}
