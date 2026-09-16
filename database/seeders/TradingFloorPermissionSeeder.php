<?php

namespace Database\Seeders;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use Illuminate\Database\Seeder;

class TradingFloorPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['trading_floor.view', 'View Trading Floor Management'],
            ['trading_floor.clients.manage', 'Manage Trading Floor Customers'],
            ['trading_floor.access.manage', 'Manage Trading Floor NFC and Fingerprint Access'],
            ['trading_floor.commissions.manage', 'Manage Trading Floor Commission Invoices'],
            ['trading_floor.payments.manage', 'Manage Trading Floor Commission Payments'],
            ['trading_floor.reports.view', 'View Trading Floor Reports'],
            ['trading_floor.settings.manage', 'Manage Trading Floor Settings'],
        ];

        $permissionIds = [];

        foreach ($permissions as [$code, $name]) {
            $permission = AdminPermission::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'module' => 'TRADING_FLOOR',
                    'description' => null,
                ]
            );

            $permissionIds[] = $permission->id;
        }

        $superAdmin = AdminRole::where('code', 'SUPER_ADMIN')->first();

        if ($superAdmin !== null) {
            $superAdmin->permissions()->syncWithoutDetaching($permissionIds);
        }
    }
}
