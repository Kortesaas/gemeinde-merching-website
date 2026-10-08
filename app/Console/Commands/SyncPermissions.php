<?php

namespace App\Console\Commands;

use App\Services\Authorization\RoleSynchronizer;
use App\Support\Authorization\Role;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('permissions:sync')]
#[Description('Synchronise the code-defined roles and permissions into the database (idempotent)')]
class SyncPermissions extends Command
{
    public function handle(RoleSynchronizer $synchronizer): int
    {
        $synchronizer->sync();

        $this->components->info('Roles and permissions synchronised.');
        $this->table(['Role', 'Permissions'], array_map(fn (Role $role) => [
            $role->value,
            count($role->permissions()),
        ], Role::cases()));

        return self::SUCCESS;
    }
}
