<?php

namespace App\Models;

use App\Contracts\Revisionable;
use App\Contracts\Routable;
use App\Contracts\Searchable;
use App\Models\Concerns\HasDocumentPlacements;
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
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Genuinely editorial/static page. Structured information (services, people,
 * departments, documents) is referenced, not copied into the body.
 * Navigation placement is managed separately (NavigationItem).
 *
 * @property int $id
 * @property string $title
 * @property string|null $summary
 * @property string|null $body
 */
#[Fillable(['title', 'summary', 'body', 'department_id'])]
class Page extends Model implements Revisionable, Routable, Searchable
{
    use HasDocumentPlacements, HasPublication, HasPublicRoute, HasResourcePlacements, HasRevisions, HasSourceReferences, SoftDeletes, TracksEditors;

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsToMany<Person, $this>
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Person::class)->withPivot('sort_order')->withTimestamps()->orderByPivot('sort_order');
    }

    public static function documentSlots(): array
    {
        return ['downloads' => 'Downloads', 'anlagen' => 'Weitere Dokumente'];
    }

    public static function resourceSlots(): array
    {
        return ['links' => 'Links', 'online-dienste' => 'Online-Dienste'];
    }

    public function displayTitle(): string
    {
        return $this->title;
    }

    public static function defaultPathPrefix(): string
    {
        return '';
    }

    /**
     * @return list<string>
     */
    public function revisionAttributes(): array
    {
        return ['title', 'summary', 'body', 'department_id'];
    }

    /**
     * @return array<string, list<string>>
     */
    public function revisionRelations(): array
    {
        return [
            'contacts' => ['sort_order'],
            'documents' => ['slot', 'group_label', 'sort_order'],
            'externalResources' => ['slot', 'group_label', 'sort_order'],
        ];
    }

    public function toSearchDocument(): SearchDocument
    {
        return new SearchDocument($this->title, (string) $this->summary, [], (string) $this->body);
    }
}
