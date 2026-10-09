<?php

namespace App\Models;

use App\Contracts\Revisionable;
use App\Contracts\Routable;
use App\Contracts\Searchable;
use App\Enums\LocationType;
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
 * Municipal/community place (Rathaus, Bauhof, Wertstoffsammelstelle, …).
 * Maps are only linked (map_resource), never embedded.
 *
 * @property int $id
 * @property string $name
 * @property LocationType $type
 * @property bool $is_active
 */
#[Fillable(['name', 'type', 'description', 'street', 'postal_code', 'city', 'phone', 'email', 'opening_hours', 'accessibility_note', 'latitude', 'longitude', 'map_resource_id', 'is_active', 'sort_order'])]
class Location extends Model implements Revisionable, Routable, Searchable
{
    use HasPublicRoute, HasRevisions, HasSourceReferences, SoftDeletes;

    protected function casts(): array
    {
        return ['type' => LocationType::class, 'is_active' => 'boolean', 'sort_order' => 'integer', 'latitude' => 'decimal:6', 'longitude' => 'decimal:6'];
    }

    /**
     * @return BelongsTo<ExternalResource, $this>
     */
    public function mapResource(): BelongsTo
    {
        return $this->belongsTo(ExternalResource::class, 'map_resource_id');
    }

    /**
     * @return HasMany<Department, $this>
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
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
        return '/orte';
    }

    /**
     * @return list<string>
     */
    public function revisionAttributes(): array
    {
        return ['seo_title', 'meta_description', 'seo_noindex', 'name', 'type', 'description', 'street', 'postal_code', 'city', 'phone', 'email', 'opening_hours', 'accessibility_note', 'latitude', 'longitude', 'map_resource_id', 'is_active', 'sort_order'];
    }

    public function toSearchDocument(): SearchDocument
    {
        return new SearchDocument($this->name, (string) $this->description, [$this->type->label(), (string) $this->street]);
    }
}
