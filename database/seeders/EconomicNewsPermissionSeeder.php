<?php

namespace Database\Seeders;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use Illuminate\Database\Seeder;

class EconomicNewsPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['economic_news.view', 'View Economic News Intelligence'],
            ['economic_news.events.manage', 'Manage Economic Events'],
            ['economic_news.pairs.manage', 'Manage Affected Pair Mapping'],
            ['economic_news.live_updates.manage', 'Manage Economic News Live Updates'],
            ['economic_news.overrides.manage', 'Manage Economic News Manual Overrides'],
            ['economic_news.sources.manage', 'Manage Economic News Sources'],
            ['economic_news.publish', 'Publish Economic News Intelligence'],
        ];

        $permissionIds = [];

        foreach ($permissions as [$code, $name]) {
            $permission = AdminPermission::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'module' => 'ECONOMIC_NEWS',
                    'description' => null,
                ]
            );

            $permissionIds[] = $permission->id;
        }

        // Keep this module seeder safe to run on its own. New ADMIN-29
        // permissions must become available to the existing super admin
        // without requiring the global role seeder (which may resync roles).
        $superAdmin = AdminRole::where('code', 'SUPER_ADMIN')->first();

        if ($superAdmin !== null) {
            $superAdmin->permissions()->syncWithoutDetaching($permissionIds);
        }
    }
}
