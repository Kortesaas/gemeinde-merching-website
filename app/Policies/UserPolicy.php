<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Authorization\Permission;

/**
 * Authorization for managing employee accounts (the UI follows in a later phase).
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::ManageUsers->value);
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->is($user) || $actor->can(Permission::ManageUsers->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::ManageUsers->value);
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->can(Permission::ManageUsers->value);
    }

    /**
     * Nobody may deactivate or delete their own account (prevents lock-out of
     * the last administrator by accident).
     */
    public function delete(User $actor, User $user): bool
    {
        return ! $actor->is($user) && $actor->can(Permission::ManageUsers->value);
    }
}
