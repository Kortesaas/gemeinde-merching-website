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

        foreach (Permission::all() as $permission) {
            $this->assertTrue($admin->can($permission), $permission);
        }
    }

    public function test_editorial_roles_follow_least_privilege(): void
    {
        foreach ([Role::Chefredaktion, Role::Fachbereichsredaktion, Role::Veranstaltungsredaktion, Role::Reviewer] as $role) {
            $user = $this->createUser($role);

            $this->assertTrue($user->can(Permission::AccessAdmin->value), $role->value);
            $this->assertFalse($user->can('user.edit'), $role->value);
            $this->assertFalse($user->can('document.force-delete'), $role->value);
            $this->assertFalse($user->can(Permission::ViewAuditLog->value), $role->value);
        }
    }

    public function test_user_policy_uses_permissions(): void
    {
        $admin = $this->createUser(Role::Administrator);
        $reviewer = $this->createUser(Role::Reviewer);

        $this->assertTrue($admin->can('create', User::class));
        $this->assertTrue($admin->can('update', $reviewer));
        $this->assertTrue($admin->can('deactivate', $reviewer));
        $this->assertFalse($admin->can('deactivate', $admin), 'Administrators must not deactivate themselves.');
        $this->assertFalse($admin->can('delete', $reviewer), 'Accounts are deactivated, never deleted.');

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
        $this->assertDatabaseCount('permissions', count(Permission::all()));
    }

    public function test_permission_sync_removes_permissions_no_longer_defined_in_code(): void
    {
        $this->artisan('permissions:sync')->assertSuccessful();
        \Spatie\Permission\Models\Permission::findOrCreate('users.manage', 'web');

        $this->artisan('permissions:sync')->assertSuccessful();

        $this->assertDatabaseMissing('permissions', ['name' => 'users.manage']);
    }
}
