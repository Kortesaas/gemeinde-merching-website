<?php

namespace App\Models;

use App\Contracts\Proposable;
use App\Contracts\Routable;
use App\Contracts\Searchable;
use App\Models\Concerns\HasDocumentPlacements;
use App\Models\Concerns\HasMedia;
use App\Models\Concerns\HasProposals;
use App\Models\Concerns\HasPublication;
use App\Models\Concerns\HasPublicRoute;
use App\Models\Concerns\HasResourcePlacements;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\HasSourceReferences;
use App\Models\Concerns\TracksEditors;
use App\Support\Search\SearchDocument;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bürgerservice-Leistung (A–Z, detail pages, Lebenslagen, search).
 * Its URL is independent of category or navigation.
 *
 * @property int $id
 * @property string $title
 * @property string|null $sort_title
 * @property string|null $summary
 * @property string|null $body
 */
#[Fillable(['title', 'sort_title', 'summary', 'body', 'category_id', 'online_service_resource_id', 'sort_order'])]
class Service extends Model implements Proposable, Routable, Searchable
{
    use HasDocumentPlacements, HasMedia, HasProposals, HasPublication, HasPublicRoute, HasResourcePlacements, HasRevisions, HasSourceReferences, SoftDeletes, TracksEditors;

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<ExternalResource, $this>
     */
    public function onlineService(): BelongsTo
    {
        return $this->belongsTo(ExternalResource::class, 'online_service_resource_id');
    }

    /**
     * @return HasMany<ServiceAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(ServiceAlias::class)->orderBy('alias');
    }

    /**
     * @return BelongsToMany<Department, $this>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class)->withPivot('sort_order')->withTimestamps()->orderByPivot('sort_order');
    }

    /**
     * @return BelongsToMany<Person, $this>
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Person::class)->withPivot('sort_order')->withTimestamps()->orderByPivot('sort_order');
    }

    /**
     * @return BelongsToMany<Service, $this>
     */
    public function relatedServices(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'related_services', 'service_id', 'related_service_id')
            ->withPivot('sort_order')->withTimestamps()->orderByPivot('sort_order');
    }

    /**
     * @return BelongsToMany<LifeSituation, $this>
     */
    public function lifeSituations(): BelongsToMany
    {
        return $this->belongsToMany(LifeSituation::class)->withPivot('sort_order')->withTimestamps();
    }

    public static function documentSlots(): array
    {
        return ['formulare' => 'Formulare', 'merkblaetter' => 'Merkblätter & Informationen'];
    }

    public static function resourceSlots(): array
    {
        return ['online' => 'Online beantragen', 'links' => 'Weitere Informationen'];
    }

    public function displayTitle(): string
    {
        return $this->title;
    }

    public static function defaultPathPrefix(): string
    {
        return '/buergerservice';
    }

    /**
     * @return list<string>
     */
    public function revisionAttributes(): array
    {
        return ['seo_title', 'meta_description', 'seo_noindex', 'title', 'sort_title', 'summary', 'body', 'category_id', 'online_service_resource_id', 'sort_order'];
    }

    /**
     * @return array<string, list<string>>
     */
    public function revisionRelations(): array
    {
        return [
            'departments' => ['sort_order'],
            'contacts' => ['sort_order'],
            'relatedServices' => ['sort_order'],
            'media' => ['sort_order'],
            'documents' => ['slot', 'group_label', 'sort_order'],
            'externalResources' => ['slot', 'group_label', 'sort_order'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public function revisionCollections(): array
    {
        return ['aliases' => ['alias']];
    }

    public function toSearchDocument(): SearchDocument
    {
        return new SearchDocument(
            $this->title,
            (string) $this->summary,
            array_values(array_filter([...$this->aliases->pluck('alias')->all(), $this->category?->name])),
            (string) $this->body,
        );
    }
}
