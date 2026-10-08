<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Authorization\Ability;
use App\Support\Authorization\ContentType;
use Illuminate\Database\Eloquent\Model;

/**
 * Permission-based authorization shared by all content types. Subclasses only
 * name their ContentType. Views and controllers call these abilities (via
 *
 * @can / $this->authorize) – never role names.
 *
 * Editing vs. publishing: users with "edit" but without "publish" may only
 * change drafts. Content that is or was public (or scheduled) is locked for
 * them until the review workflow exists, so an editor can never change what
 * the public sees without a publisher.
 */
abstract class ContentPolicy
{
    abstract protected function type(): ContentType;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, Ability::View);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->allows($user, Ability::View);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, Ability::Create);
    }

    public function update(User $user, Model $model): bool
    {
        if ($this->isTrashed($model) || ! $this->allows($user, Ability::Edit)) {
            return false;
        }

        return ! $this->isLocked($model) || $this->allows($user, Ability::Publish);
    }

    public function publish(User $user, ?Model $model = null): bool
    {
        return $this->allows($user, Ability::Publish);
    }

    public function archive(User $user, ?Model $model = null): bool
    {
        return $this->allows($user, Ability::Archive);
    }

    public function delete(User $user, Model $model): bool
    {
        if ($this->isTrashed($model) || ! $this->allows($user, Ability::Delete)) {
            return false;
        }

        // Removing live content takes it offline – that is a publishing decision.
        return ! $this->isLocked($model) || $this->allows($user, Ability::Publish);
    }

    public function restore(User $user, Model $model): bool
    {
        return $this->isTrashed($model) && $this->allows($user, Ability::Delete);
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return $this->isTrashed($model) && $this->allows($user, Ability::ForceDelete);
    }

    public function viewRevisions(User $user, Model $model): bool
    {
        return $this->view($user, $model) && method_exists($model, 'revisions');
    }

    public function restoreRevision(User $user, Model $model): bool
    {
        return $this->update($user, $model) && method_exists($model, 'revisions');
    }

    protected function allows(User $user, Ability $ability): bool
    {
        return in_array($ability, $this->type()->abilities(), true)
            && $user->can($this->type()->permission($ability));
    }

    private function isLocked(Model $model): bool
    {
        return method_exists($model, 'isPublicationLocked') && $model->isPublicationLocked();
    }

    private function isTrashed(Model $model): bool
    {
        return method_exists($model, 'trashed') && $model->trashed();
    }
}
