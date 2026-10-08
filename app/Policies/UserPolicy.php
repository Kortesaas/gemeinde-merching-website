<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Authorization\Ability;
use App\Support\Authorization\ContentType;

/**
 * Authorization for managing employee accounts (user.* permissions).
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(ContentType::User->permission(Ability::View));
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->is($user) || $actor->can(ContentType::User->permission(Ability::View));
    }

    public function create(User $actor): bool
    {
        return $actor->can(ContentType::User->permission(Ability::Create));
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->can(ContentType::User->permission(Ability::Edit));
    }

    /**
     * Accounts are deactivated, never deleted (audit/revision history refers
     * to them). Nobody may deactivate their own account.
     */
    public function deactivate(User $actor, User $user): bool
    {
        return ! $actor->is($user) && $actor->can(ContentType::User->permission(Ability::Edit));
    }

    public function delete(User $actor, User $user): bool
    {
        return false;
    }
}
