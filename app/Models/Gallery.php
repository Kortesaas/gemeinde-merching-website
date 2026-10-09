<?php

namespace App\Models;

use App\Contracts\Proposable;
use App\Contracts\Routable;
use App\Contracts\Searchable;
use App\Models\Concerns\HasProposals;
use App\Models\Concerns\HasPublication;
use App\Models\Concerns\HasPublicRoute;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\TracksEditors;
use App\Support\Search\SearchDocument;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $title
 * @property string|null $description
 */
#[Fillable(['title', 'description'])]
class Gallery extends Model implements Proposable, Routable, Searchable
{
    use HasProposals, HasPublication, HasPublicRoute, HasRevisions, SoftDeletes, TracksEditors;

    public function displayTitle(): string
    {
        return $this->title;
    }

    /** @return list<string> */
    public function revisionAttributes(): array
    {
        return ['seo_title', 'meta_description', 'seo_noindex', 'title', 'description'];
    }

    public static function createsRouteAutomatically(): bool
    {
        return false;
    }

    public static function defaultPathPrefix(): string
    {
        return '/galerien';
    }

    public function toSearchDocument(): SearchDocument
    {
        return new SearchDocument($this->title, (string) $this->description);
    }

    /** @return HasMany<GalleryItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(GalleryItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function revisionCollections(): array
    {
        return ['items' => GalleryItem::COLUMNS];
    }
}
