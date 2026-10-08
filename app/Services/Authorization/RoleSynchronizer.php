<?php

namespace App\Services\Authorization;

use App\Support\Authorization\Permission;
use App\Support\Authorization\Role;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Synchronises the code-defined roles and permissions into the database.
 * Idempotent; safe to run on every deployment.
 */
class RoleSynchronizer
{
    public function __construct(private readonly PermissionRegistrar $registrar) {}

    public function sync(): void
    {
        $this->registrar->forgetCachedPermissions();

        DB::transaction(function () {
            foreach (Permission::cases() as $permission) {
                PermissionModel::findOrCreate($permission->value, 'web');
            }

            foreach (Role::cases() as $role) {
                RoleModel::findOrCreate($role->value, 'web')->syncPermissions(
                    array_map(fn (Permission $p) => $p->value, $role->permissions()),
                );
            }
        });

        $this->registrar->forgetCachedPermissions();
    }
}
