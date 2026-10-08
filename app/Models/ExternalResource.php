<?php

namespace App\Models;

use App\Contracts\Proposable;
use App\Contracts\Searchable;
use App\Enums\ExternalResourceType;
use App\Models\Concerns\HasProposals;
use App\Models\Concerns\HasPublication;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\HasSourceReferences;
use App\Models\Concerns\TracksEditors;
use App\Support\Search\SearchDocument;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A managed external link / online service (e.g. Bürgerserviceportal,
 * BayernAtlas). Maintained once and placed wherever it is needed; its
 * publication window doubles as validity period.
 *
 * @property int $id
 * @property string $title
 * @property string $url
 * @property string|null $description
 * @property ExternalResourceType $type
 * @property string|null $provider_name
 * @property string|null $privacy_note
 */
#[Fillable(['title', 'url', 'description', 'type', 'provider_name', 'privacy_note'])]
class ExternalResource extends Model implements Proposable, Searchable
{
    use HasProposals, HasPublication, HasRevisions, HasSourceReferences, SoftDeletes, TracksEditors;

    protected function casts(): array
    {
        return ['type' => ExternalResourceType::class];
    }

    public function displayTitle(): string
    {
        return $this->title;
    }

    /**
     * @return list<string>
     */
    public function revisionAttributes(): array
    {
        return ['title', 'url', 'description', 'type', 'provider_name', 'privacy_note'];
    }

    /**
     * Owners that place this resource ("Wo wird dieser Link verwendet?").
     *
     * @return array<string, BelongsToMany<Model, $this>>
     */
    public function placementRelations(): array
    {
        return [
            'articles' => $this->placedOn(Article::class),
            'events' => $this->placedOn(Event::class),
            'services' => $this->placedOn(Service::class),
            'lifeSituations' => $this->placedOn(LifeSituation::class),
            'pages' => $this->placedOn(Page::class),
        ];
    }

    /**
     * @param  class-string<Model>  $owner
     * @return BelongsToMany<Model, $this>
     */
    private function placedOn(string $owner): BelongsToMany
    {
        return $this->belongsToMany($owner)->withPivot(['slot', 'group_label', 'sort_order'])->withTrashed(); // @phpstan-ignore method.notFound
    }

    public function toSearchDocument(): SearchDocument
    {
        return new SearchDocument($this->title, (string) $this->description, array_values(array_filter([$this->provider_name, $this->type->label()])));
    }
}
