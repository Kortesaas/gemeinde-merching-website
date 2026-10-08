<?php

namespace App\Models;

use App\Contracts\Revisionable;
use App\Contracts\Routable;
use App\Contracts\Searchable;
use App\Models\Concerns\HasPublicRoute;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\HasSourceReferences;
use App\Support\Search\SearchDocument;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Amt / Sachgebiet. Phone and e-mail exist only here; every page that shows
 * the department reads them from this record.
 *
 * @property int $id
 * @property string $name
 * @property string|null $phone
 * @property string|null $email
 * @property bool $is_active
 */
#[Fillable(['name', 'short_name', 'description', 'phone', 'email', 'location_id', 'opening_hours', 'is_active', 'sort_order'])]
class Department extends Model implements Revisionable, Routable, Searchable
{
    use HasPublicRoute, HasRevisions, HasSourceReferences, SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsToMany<Person, $this>
     */
    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class)->withPivot(['function_label', 'sort_order'])->withTimestamps()->orderByPivot('sort_order');
    }

    /**
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)->withPivot('sort_order')->withTimestamps();
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
        return '/rathaus/aemter';
    }

    /**
     * @return list<string>
     */
    public function revisionAttributes(): array
    {
        return ['name', 'short_name', 'description', 'phone', 'email', 'location_id', 'opening_hours', 'is_active', 'sort_order'];
    }

    /**
     * @return array<string, list<string>>
     */
    public function revisionRelations(): array
    {
        return ['people' => ['function_label', 'sort_order']];
    }

    public function toSearchDocument(): SearchDocument
    {
        return new SearchDocument($this->name, (string) $this->description, $this->short_name ? [$this->short_name] : []);
    }
}
