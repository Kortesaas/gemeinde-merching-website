<?php

namespace App\Models;

use App\Contracts\Revisionable;
use App\Contracts\Routable;
use App\Enums\NavigationMenu;
use App\Exceptions\DomainRuleViolation;
use App\Models\Concerns\HasRevisions;
use App\Rules\SafeUrl;
use App\Services\Navigation\NavigationManager;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Menu entry. Navigation is NOT the URL structure: an item points to a
 * record's canonical route (whose path may change without breaking the menu)
 * or to an external URL, and can be moved freely.
 *
 * @property int $id
 * @property NavigationMenu $menu
 * @property string $label
 * @property string|null $url
 */
#[Fillable(['menu', 'parent_id', 'label', 'public_route_id', 'external_resource_id', 'url', 'sort_order', 'is_active'])]
class NavigationItem extends Model implements Revisionable
{
    use HasRevisions;

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            NavigationItem::query()->orderBy('id')->lockForUpdate()->get();
            app(NavigationManager::class)->validate($item);
        });
        static::deleting(function (self $item) {
            if ($item->children()->exists()) {
                throw new DomainRuleViolation('Bitte zuerst untergeordnete Navigationseinträge verschieben oder entfernen.');
            }
        });
    }

    /** @return list<string> */
    public function revisionAttributes(): array
    {
        return ['menu', 'parent_id', 'label', 'public_route_id', 'external_resource_id', 'url', 'sort_order', 'is_active'];
    }

    protected function casts(): array
    {
        return ['menu' => NavigationMenu::class, 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /**
     * @return BelongsTo<NavigationItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(NavigationItem::class, 'parent_id');
    }

    /**
     * @return HasMany<NavigationItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(NavigationItem::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * @return BelongsTo<PublicRoute, $this>
     */
    public function publicRoute(): BelongsTo
    {
        return $this->belongsTo(PublicRoute::class);
    }

    public function href(): ?string
    {
        return $this->resolveTarget()['href'];
    }

    /**
     * Current public target; menus resolve it once per render via NavigationNode.
     *
     * @return array{href:?string,target:?Model}
     */
    public function resolveTarget(): array
    {
        $route = $this->publicRoute()->first();

        if ($route !== null) {
            $model = $route->routable()->first();
            $public = $route->is_active && $route->is_canonical && $model instanceof Routable && $model->isPubliclyReachable();

            return ['href' => $public ? $route->path : null, 'target' => $public ? $model : null];
        }
        $resource = ExternalResource::query()->whereKey($this->getAttribute('external_resource_id'))->first();
        if ($resource !== null) {
            return ['href' => $resource->isPubliclyReachable() ? $resource->url : null, 'target' => null];
        }

        return ['href' => $this->url !== null && SafeUrl::isSafe($this->url) ? $this->url : null, 'target' => null];
    }

    public function displayTitle(): string
    {
        return $this->label;
    }
}
