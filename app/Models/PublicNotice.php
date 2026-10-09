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
 * Amtliche Bekanntmachung – deliberately its own model, not an Article.
 * The display window is publish_at/expires_at; after it ends the notice stays
 * reachable in the public archive (keepsPublicArchive).
 *
 * @property int $id
 * @property string $title
 * @property string|null $summary
 * @property string|null $body
 */
#[Fillable(['title', 'summary', 'body', 'category_id', 'published_on'])]
class PublicNotice extends Model implements Proposable, Routable, Searchable
{
    use HasDocumentPlacements, HasMedia, HasProposals, HasPublication, HasPublicRoute, HasRevisions, HasSourceReferences, SoftDeletes, TracksEditors;

    protected function casts(): array
    {
        return ['published_on' => 'immutable_date'];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsToMany<Page, $this>
     */
    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class)->withTimestamps();
    }

    /**
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)->withTimestamps();
    }

    public static function documentSlots(): array
    {
        return ['bekanntmachung' => 'Bekanntmachung', 'anlagen' => 'Anlagen'];
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
        return '/bekanntmachungen';
    }

    /**
     * @return list<string>
     */
    public function revisionAttributes(): array
    {
        return ['seo_title', 'meta_description', 'seo_noindex', 'title', 'summary', 'body', 'category_id', 'published_on'];
    }

    /**
     * @return array<string, list<string>>
     */
    public function revisionRelations(): array
    {
        return [
            'pages' => [],
            'services' => [],
            'media' => ['sort_order'],
            'documents' => ['slot', 'group_label', 'sort_order'],
        ];
    }

    public function toSearchDocument(): SearchDocument
    {
        return new SearchDocument($this->title, (string) $this->summary, array_filter([$this->category?->name]), (string) $this->body);
    }
}
