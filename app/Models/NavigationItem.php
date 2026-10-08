<?php

namespace App\Models;

use App\Enums\NavigationMenu;
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
#[Fillable(['menu', 'parent_id', 'label', 'public_route_id', 'url', 'sort_order', 'is_active'])]
class NavigationItem extends Model
{
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
        $route = $this->publicRoute()->first();

        return $route !== null ? $route->path : $this->url;
    }

    public function displayTitle(): string
    {
        return $this->label;
    }
}
