<?php

namespace App\Policies;

use App\Admin\ResourceRegistry;
use App\Enums\ProposalStatus;
use App\Models\ContentProposal;
use App\Models\User;
use App\Support\Authorization\Ability;
use App\Support\Authorization\ContentType;

/**
 * Change proposals: authors (edit permission) draft, submit and withdraw their
 * own proposals; publishers (publish permission of the content type) review
 * them. By default nobody approves their own proposal (four-eyes principle,
 * config admin.proposals.allow_self_approval).
 */
class ContentProposalPolicy
{
    public function view(User $user, ContentProposal $proposal): bool
    {
        return $this->isAuthor($user, $proposal) || $this->allows($user, $proposal, Ability::View);
    }

    public function update(User $user, ContentProposal $proposal): bool
    {
        return $this->isAuthor($user, $proposal)
            && $proposal->status->isOpen()
            && ! $this->recordTrashed($proposal)
            && $this->allows($user, $proposal, Ability::Edit);
    }

    public function submit(User $user, ContentProposal $proposal): bool
    {
        return $this->update($user, $proposal) && $proposal->status === ProposalStatus::Draft;
    }

    public function withdraw(User $user, ContentProposal $proposal): bool
    {
        return $this->isAuthor($user, $proposal) && $proposal->status->isOpen();
    }

    /**
     * Approve-and-publish or reject.
     */
    public function review(User $user, ContentProposal $proposal): bool
    {
        return $proposal->status === ProposalStatus::Submitted
            && $this->allows($user, $proposal, Ability::Publish)
            && (config('admin.proposals.allow_self_approval') === true || ! $this->isAuthor($user, $proposal));
    }

    public function apply(User $user, ContentProposal $proposal): bool
    {
        return $this->review($user, $proposal) && ! $this->recordTrashed($proposal);
    }

    private function isAuthor(User $user, ContentProposal $proposal): bool
    {
        return $proposal->author_id !== null && $proposal->author_id === $user->getKey();
    }

    private function allows(User $user, ContentProposal $proposal, Ability $ability): bool
    {
        $type = $this->type($proposal);

        return $type !== null && $user->can($type->permission($ability));
    }

    private function type(ContentProposal $proposal): ?ContentType
    {
        $record = $proposal->proposable;

        return $record === null ? null : ResourceRegistry::forModel($record)?->type();
    }

    private function recordTrashed(ContentProposal $proposal): bool
    {
        $record = $proposal->proposable;

        return $record === null || (method_exists($record, 'trashed') && $record->trashed());
    }
}
