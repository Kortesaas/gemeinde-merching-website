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
 * Idempotent; safe to run on every deployment. Permissions that no longer
 * exist in code are removed (code is the single source of truth).
 */
class RoleSynchronizer
{
    public function __construct(private readonly PermissionRegistrar $registrar) {}

    public function sync(): void
    {
        $this->registrar->forgetCachedPermissions();

        DB::transaction(function () {
            $names = Permission::all();

            foreach ($names as $name) {
                PermissionModel::findOrCreate($name, 'web');
            }

            PermissionModel::query()->where('guard_name', 'web')->whereNotIn('name', $names)->delete();

            foreach (Role::cases() as $role) {
                RoleModel::findOrCreate($role->value, 'web')->syncPermissions($role->permissions());
            }
        });

        $this->registrar->forgetCachedPermissions();
    }
}
