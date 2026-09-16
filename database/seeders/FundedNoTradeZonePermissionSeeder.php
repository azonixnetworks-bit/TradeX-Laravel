<?php

namespace Database\Seeders;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use Illuminate\Database\Seeder;

class FundedNoTradeZonePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['funded_no_trade_zone.view', 'View Funded No Trade Zone'],
            ['funded_no_trade_zone.rules.manage', 'Manage Funded No Trade Zone Rules'],
            ['funded_no_trade_zone.overrides.manage', 'Manage Funded No Trade Zone Overrides'],
        ];

        $permissionIds = [];

        foreach ($permissions as [$code, $name]) {
            $permission = AdminPermission::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'module' => 'FUNDED_NO_TRADE_ZONE',
                    'description' => null,
                ]
            );

            $permissionIds[] = $permission->id;
        }

        $superAdmin = AdminRole::query()->where('code', 'SUPER_ADMIN')->first();

        if ($superAdmin !== null) {
            $superAdmin->permissions()->syncWithoutDetaching($permissionIds);
        }
    }
}
