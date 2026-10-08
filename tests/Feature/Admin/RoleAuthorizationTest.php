<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Authorization\Permission;
use App\Support\Authorization\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_holds_all_permissions(): void
    {
        $admin = $this->createUser(Role::Administrator);

        foreach (Permission::cases() as $permission) {
            $this->assertTrue($admin->can($permission->value), $permission->value);
        }
    }

    public function test_editorial_roles_follow_least_privilege(): void
    {
        foreach ([Role::Chefredaktion, Role::Fachbereichsredaktion, Role::Veranstaltungsredaktion, Role::Reviewer] as $role) {
            $user = $this->createUser($role);

            $this->assertTrue($user->can(Permission::AccessAdmin->value), $role->value);
            $this->assertFalse($user->can(Permission::ManageUsers->value), $role->value);
            $this->assertFalse($user->can(Permission::ViewAuditLog->value), $role->value);
        }
    }

    public function test_user_policy_uses_permissions(): void
    {
        $admin = $this->createUser(Role::Administrator);
        $reviewer = $this->createUser(Role::Reviewer);

        $this->assertTrue($admin->can('create', User::class));
        $this->assertTrue($admin->can('update', $reviewer));
        $this->assertTrue($admin->can('delete', $reviewer));
        $this->assertFalse($admin->can('delete', $admin), 'Administrators must not delete themselves.');

        $this->assertFalse($reviewer->can('create', User::class));
        $this->assertFalse($reviewer->can('update', $admin));
        $this->assertTrue($reviewer->can('view', $reviewer));
        $this->assertFalse($reviewer->can('view', $admin));
    }

    public function test_permission_sync_is_idempotent(): void
    {
        $this->artisan('permissions:sync')->assertSuccessful();
        $this->artisan('permissions:sync')->assertSuccessful();

        $this->assertDatabaseCount('roles', count(Role::cases()));
        $this->assertDatabaseCount('permissions', count(Permission::cases()));
    }
}
