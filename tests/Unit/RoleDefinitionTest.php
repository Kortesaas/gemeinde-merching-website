<?php

namespace Tests\Unit;

use App\Support\Authorization\Ability;
use App\Support\Authorization\ContentType;
use App\Support\Authorization\Permission;
use App\Support\Authorization\Role;
use PHPUnit\Framework\TestCase;

class RoleDefinitionTest extends TestCase
{
    public function test_only_administrators_can_manage_users_and_force_delete(): void
    {
        foreach (Role::cases() as $role) {
            $isAdmin = $role === Role::Administrator;
            $this->assertSame($isAdmin, in_array(ContentType::User->permission(Ability::Edit), $role->permissions(), true), $role->value);
            $this->assertSame($isAdmin, in_array(ContentType::Document->permission(Ability::ForceDelete), $role->permissions(), true), $role->value);
            $this->assertSame($isAdmin, in_array(Permission::ViewAuditLog->value, $role->permissions(), true), $role->value);
        }
    }

    public function test_administrator_has_every_permission(): void
    {
        $this->assertSame(Permission::all(), Role::Administrator->permissions());
    }

    public function test_role_permissions_only_reference_existing_permissions(): void
    {
        foreach (Role::cases() as $role) {
            $this->assertSame([], array_diff($role->permissions(), Permission::all()), $role->value);
            $this->assertContains(Permission::AccessAdmin->value, $role->permissions(), $role->value);
        }
    }

    public function test_permission_names_follow_type_dot_ability(): void
    {
        foreach (Permission::all() as $name) {
            $this->assertMatchesRegularExpression('/^[a-z-]+\.[a-z-]+$/', $name);
        }
        $this->assertContains('article.publish', Permission::all());
        $this->assertContains('redirect.edit', Permission::all());
        $this->assertNotContains('redirect.publish', Permission::all());
    }

    public function test_every_role_has_a_german_label(): void
    {
        foreach (Role::cases() as $role) {
            $this->assertNotSame('', $role->label());
        }
    }
}
