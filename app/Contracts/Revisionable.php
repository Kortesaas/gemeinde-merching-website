<?php

namespace App\Contracts;

use App\Models\ContentRevision;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A record with editorial history (implemented via App\Models\Concerns\HasRevisions).
 */
interface Revisionable
{
    /**
     * @return list<string>
     */
    public function revisionAttributes(): array;

    /**
     * @return array<string, list<string>>
     */
    public function revisionRelations(): array;

    /**
     * @return MorphMany<ContentRevision, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function revisions(): MorphMany;
}
