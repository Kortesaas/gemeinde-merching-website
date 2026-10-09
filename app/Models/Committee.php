<?php

namespace App\Models;

use App\Contracts\Proposable;
use App\Models\Concerns\HasProposals;
use App\Models\Concerns\HasPublication;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\TracksEditors;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property string $title */
#[Fillable(['title', 'description', 'council_term_id', 'sort_order'])]
class Committee extends Model implements Proposable
{
    use HasProposals, HasPublication, HasRevisions, SoftDeletes, TracksEditors;

    public function keepsPublicArchive(): bool
    {
        return true;
    }

    public function displayTitle(): string
    {
        return $this->title;
    }

    /** @return list<string> */
    public function revisionAttributes(): array
    {
        return ['title', 'description', 'council_term_id', 'sort_order'];
    }

    /** @return BelongsTo<CouncilTerm, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(CouncilTerm::class, 'council_term_id');
    }

    /** @return HasMany<CommitteeMembership, $this> */
    public function committeeMemberships(): HasMany
    {
        return $this->hasMany(CommitteeMembership::class)->orderBy('sort_order')->orderBy('id');
    }

    public function revisionCollections(): array
    {
        return ['committeeMemberships' => CommitteeMembership::COLUMNS];
    }
}
