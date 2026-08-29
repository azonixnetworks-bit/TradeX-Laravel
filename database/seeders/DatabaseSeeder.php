<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminRoleSeeder::class,
            AdminPermissionSeeder::class,
            AdminRolePermissionSeeder::class,
            SuperAdminSeeder::class,
            AffiliateStageSeeder::class,
            ProductSeeder::class,
        ]);
    }
}
