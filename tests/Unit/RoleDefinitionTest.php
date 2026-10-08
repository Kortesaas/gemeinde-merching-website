<?php

namespace Tests\Unit;

use App\Support\Authorization\Permission;
use App\Support\Authorization\Role;
use PHPUnit\Framework\TestCase;

class RoleDefinitionTest extends TestCase
{
    public function test_only_administrators_can_manage_users(): void
    {
        foreach (Role::cases() as $role) {
            $this->assertSame(
                $role === Role::Administrator,
                in_array(Permission::ManageUsers, $role->permissions(), true),
                $role->value,
            );
        }
    }

    public function test_every_role_has_a_german_label(): void
    {
        foreach (Role::cases() as $role) {
            $this->assertNotSame('', $role->label());
        }
    }
}
