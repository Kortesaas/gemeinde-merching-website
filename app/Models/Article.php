<?php

namespace App\Models;

use App\Contracts\Proposable;
use App\Contracts\Routable;
use App\Contracts\Searchable;
use App\Models\Concerns\HasContentBlocks;
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
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * News post ("Aktuelles").
 *
 * @property int $id
 * @property string $title
 * @property string|null $summary
 * @property string|null $body
 * @property string|null $author_name
 * @property bool $is_featured
 */
#[Fillable(['title', 'summary', 'body', 'category_id', 'department_id', 'author_name', 'is_featured'])]
class Article extends Model implements Proposable, Routable, Searchable
{
    use HasContentBlocks, HasDocumentPlacements, HasMedia, HasProposals, HasPublication, HasPublicRoute, HasResourcePlacements, HasRevisions, HasSourceReferences, SoftDeletes, TracksEditors;

    protected function casts(): array
    {
        return ['is_featured' => 'boolean'];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
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
        return ['anhaenge' => 'Anhänge'];
    }

    public static function resourceSlots(): array
    {
        return ['links' => 'Weiterführende Links'];
    }

    public function displayTitle(): string
    {
        return $this->title;
    }

    public function keepsPublicArchive(): bool
    {
        return true;
    }

    public static function defaultPathPrefix(): string
    {
        return '/aktuelles';
    }

    /**
     * @return list<string>
     */
    public function revisionAttributes(): array
    {
        return ['seo_title', 'meta_description', 'seo_noindex', 'title', 'summary', 'body', 'category_id', 'department_id', 'author_name', 'is_featured'];
    }

    /**
     * @return array<string, list<string>>
     */
    public function revisionRelations(): array
    {
        return [
            'tags' => [],
            'contacts' => ['sort_order'],
            'media' => ['sort_order'],
            'documents' => ['slot', 'group_label', 'sort_order'],
            'externalResources' => ['slot', 'group_label', 'sort_order'],
        ];
    }

    /** @return array<string, list<string>> */
    public function revisionCollections(): array
    {
        return ['blocks' => ContentBlock::COLUMNS];
    }

    public function toSearchDocument(): SearchDocument
    {
        return new SearchDocument(
            $this->title,
            (string) $this->summary,
            array_values(array_filter([...$this->tags->pluck('name')->all(), $this->category?->name])),
            (string) $this->body,
        );
    }
}
