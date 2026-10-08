<?php

namespace App\Models;

use App\Contracts\Revisionable;
use App\Contracts\Routable;
use App\Contracts\Searchable;
use App\Enums\OrganizationType;
use App\Models\Concerns\HasPublicRoute;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\HasSourceReferences;
use App\Support\Search\SearchDocument;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One directory model for Vereine, Gewerbe and Gastronomie (type).
 *
 * @property int $id
 * @property string $name
 * @property OrganizationType $type
 * @property bool $is_active
 */
#[Fillable(['name', 'type', 'description', 'category_id', 'contact_name', 'street', 'postal_code', 'city', 'phone', 'email', 'website', 'is_active', 'sort_order'])]
class Organization extends Model implements Revisionable, Routable, Searchable
{
    use HasPublicRoute, HasRevisions, HasSourceReferences, SoftDeletes;

    protected function casts(): array
    {
        return ['type' => OrganizationType::class, 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<OrganizationLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(OrganizationLink::class)->orderBy('sort_order');
    }

    public function displayTitle(): string
    {
        return $this->name;
    }

    public function isPubliclyReachable(): bool
    {
        return $this->is_active && ! $this->trashed();
    }

    /**
     * Opt-in: a public detail page exists only if an editor enters a path.
     */
    public static function createsRouteAutomatically(): bool
    {
        return false;
    }

    public static function defaultPathPrefix(): string
    {
        return '/verzeichnis';
    }

    /**
     * @return list<string>
     */
    public function revisionAttributes(): array
    {
        return ['name', 'type', 'description', 'category_id', 'contact_name', 'street', 'postal_code', 'city', 'phone', 'email', 'website', 'is_active', 'sort_order'];
    }

    public function toSearchDocument(): SearchDocument
    {
        return new SearchDocument($this->name, (string) $this->description, array_values(array_filter([$this->type->label(), $this->category?->name])));
    }
}
