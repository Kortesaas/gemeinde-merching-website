<?php

namespace App\Admin;

use Illuminate\Database\Eloquent\Model;

/**
 * All admin resources, keyed by their route key.
 */
final class ResourceRegistry
{
    /** @var list<class-string> */
    public const RESOURCES = [
        Resources\MediaResource::class,
        Resources\SearchSynonymResource::class,
        Resources\SiteSettingsResource::class,
        Resources\ArticleResource::class,
        Resources\EventResource::class,
        Resources\PublicNoticeResource::class,
        Resources\DocumentResource::class,
        Resources\ExternalResourceResource::class,
        Resources\ServiceResource::class,
        Resources\LifeSituationResource::class,
        Resources\PageResource::class,
        Resources\SiteAlertResource::class,
        Resources\PersonResource::class,
        Resources\DepartmentResource::class,
        Resources\LocationResource::class,
        Resources\OrganizationResource::class,
        Resources\ContactRouteResource::class,
        Resources\CategoryResource::class,
        Resources\TagResource::class,
        Resources\NavigationItemResource::class,
        Resources\RedirectResource::class,
    ];

    /**
     * @return array<string, ContentResource<Model>>
     */
    public static function all(): array
    {
        $resources = [];
        foreach (self::RESOURCES as $class) {
            /** @var ContentResource<Model> $resource */
            $resource = app($class);
            $resources[$resource->key()] = $resource;
        }

        return $resources;
    }

    /**
     * @return ContentResource<Model>
     */
    public static function get(string $key): ContentResource
    {
        return self::all()[$key] ?? abort(404);
    }

    /**
     * @return ContentResource<Model>|null
     */
    public static function forModel(Model $model): ?ContentResource
    {
        foreach (self::all() as $resource) {
            if ($resource->model() === $model::class) {
                return $resource;
            }
        }

        return null;
    }
}
