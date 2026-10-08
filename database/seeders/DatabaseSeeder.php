<?php

namespace Database\Seeders;

use App\Services\Authorization\RoleSynchronizer;
use Illuminate\Database\Seeder;

/**
 * Seeds system data only. There are deliberately NO default user accounts or
 * credentials – create the first administrator with `php artisan admin:create`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(RoleSynchronizer $roles): void
    {
        $roles->sync();
    }
}
