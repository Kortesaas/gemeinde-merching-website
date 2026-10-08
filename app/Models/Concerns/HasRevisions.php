<?php

namespace App\Models\Concerns;

use App\Models\ContentRevision;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Editorial history. Snapshots contain ONLY the attributes and relations
 * listed by the model (explicit allowlist) – never secrets or system data.
 * Revisions are recorded by App\Services\Content\RevisionService.
 */
trait HasRevisions
{
    /**
     * @return MorphMany<ContentRevision, $this>
     */
    public function revisions(): MorphMany
    {
        return $this->morphMany(ContentRevision::class, 'revisionable')->orderByDesc('revision_number');
    }

    /**
     * Attributes captured in a revision.
     *
     * @return list<string>
     */
    abstract public function revisionAttributes(): array;

    /**
     * BelongsToMany relations captured in a revision: relation => pivot columns.
     *
     * @return array<string, list<string>>
     */
    public function revisionRelations(): array
    {
        return [];
    }
}
