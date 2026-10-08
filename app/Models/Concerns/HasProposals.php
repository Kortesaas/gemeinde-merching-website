<?php

namespace App\Models\Concerns;

use App\Models\ContentProposal;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Change proposals for this record (see App\Services\Content\ProposalService).
 * Proposals are removed when the record is permanently deleted.
 */
trait HasProposals
{
    public static function bootHasProposals(): void
    {
        static::forceDeleted(function (self $model) {
            $model->proposals()->delete();
        });
    }

    /**
     * @return MorphMany<ContentProposal, $this>
     */
    public function proposals(): MorphMany
    {
        return $this->morphMany(ContentProposal::class, 'proposable')->latest('id');
    }
}
