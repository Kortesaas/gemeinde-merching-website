<?php

namespace App\Contracts;

use App\Models\ContentProposal;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Publishable, versioned content that accepts change proposals
 * (implemented via App\Models\Concerns\HasProposals).
 */
interface Proposable extends Revisionable
{
    /**
     * @return MorphMany<ContentProposal, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function proposals(): MorphMany;

    public function isPublicationLocked(): bool;
}
