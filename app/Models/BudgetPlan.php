<?php

namespace App\Models;

use App\Contracts\Proposable;
use App\Contracts\Routable;
use App\Contracts\Searchable;
use App\Enums\AccessibilityStatus;
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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property AccessibilityStatus $accessibility_status */
#[Fillable(['year', 'topic', 'title', 'description', 'show_components', 'accessibility_status', 'accessibility_notes'])]
class BudgetPlan extends Model implements Proposable, Routable, Searchable
{
    use HasProposals, HasPublication, HasPublicRoute, HasRevisions, HasSourceReferences, SoftDeletes, TracksEditors;

    protected $attributes = ['topic' => 'Haushaltsplan', 'accessibility_status' => 'not_checked', 'show_components' => false, 'generation_status' => 'stale'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'show_components' => 'boolean', 'accessibility_status' => AccessibilityStatus::class, 'seo_noindex' => 'boolean'];
    }

    public function toSearchDocument(): SearchDocument
    {
        return new SearchDocument($this->title, (string) $this->description, [(string) $this->year, (string) $this->topic, 'Haushalt', 'Finanzen']);
    }

    public function displayTitle(): string
    {
        return $this->title;
    }

    public static function defaultPathPrefix(): string
    {
        return '/haushaltsplaene';
    }

    public function keepsPublicArchive(): bool
    {
        return true;
    }

    /** @return HasMany<BudgetComponent, $this> */
    public function components(): HasMany
    {
        return $this->hasMany(BudgetComponent::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<BudgetSource, $this> */
    public function sources(): HasMany
    {
        return $this->hasMany(BudgetSource::class)->orderBy('id');
    }

    /** @return BelongsTo<BudgetGeneration, $this> */
    public function currentGeneration(): BelongsTo
    {
        return $this->belongsTo(BudgetGeneration::class, 'current_generation_id');
    }

    /** @return HasMany<BudgetPublication, $this> */
    public function publications(): HasMany
    {
        return $this->hasMany(BudgetPublication::class)->latest('id');
    }

    /** @return list<string> */
    public function revisionAttributes(): array
    {
        return ['year', 'topic', 'title', 'description', 'show_components', 'accessibility_status', 'accessibility_notes', 'seo_title', 'meta_description', 'seo_noindex'];
    }

    /** @return array<string,list<string>> */
    public function revisionCollections(): array
    {
        return ['components' => ['budget_source_id', 'sort_order']];
    }
}
