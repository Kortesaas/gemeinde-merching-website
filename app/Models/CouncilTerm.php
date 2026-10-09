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
#[Fillable(['title', 'description', 'starts_on', 'ends_on', 'is_historical'])]
class CouncilTerm extends Model implements Proposable, Routable, Searchable
{
    use HasProposals, HasPublication, HasPublicRoute, HasRevisions, SoftDeletes, TracksEditors;

    public function displayTitle(): string
    {
        return $this->title;
    }

    /** @return list<string> */
    public function revisionAttributes(): array
    {
        return ['seo_title', 'meta_description', 'seo_noindex', 'title', 'description', 'starts_on', 'ends_on', 'is_historical'];
    }

    public static function createsRouteAutomatically(): bool
    {
        return false;
    }

    public static function defaultPathPrefix(): string
    {
        return '/wahlperioden';
    }

    public function toSearchDocument(): SearchDocument
    {
        return new SearchDocument($this->title, (string) $this->description);
    }

    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date', 'is_historical' => 'boolean'];
    }

    public function keepsPublicArchive(): bool
    {
        return true;
    }

    /** @return HasMany<CouncilMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(CouncilMembership::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<Committee, $this> */
    public function committees(): HasMany
    {
        return $this->hasMany(Committee::class)->orderBy('sort_order')->orderBy('id');
    }

    public function revisionCollections(): array
    {
        return ['memberships' => CouncilMembership::COLUMNS];
    }
}
