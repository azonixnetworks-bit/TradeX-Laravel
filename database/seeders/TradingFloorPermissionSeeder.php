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
            ['trading_floor.resources.view', 'View Trading Floor Floors, Desks and Packages'],
            ['trading_floor.floors.manage', 'Manage Trading Floor Floors'],
            ['trading_floor.desks.manage', 'Manage Trading Floor Desks and Seats'],
            ['trading_floor.packages.manage', 'Manage Trading Floor Packages'],
            ['trading_floor.memberships.view', 'View Trading Floor Memberships'],
            ['trading_floor.memberships.manage', 'Manage Trading Floor Memberships'],
            ['trading_floor.membership_payments.manage', 'Manage Trading Floor Membership Payments'],
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

        $floorAdmin = AdminRole::updateOrCreate(
            ['code' => 'TRADING_FLOOR_ADMIN'],
            [
                'name' => 'Trading Floor Admin',
                'description' => 'Trading Floor customers, access, commissions, resources, memberships and reports only.',
                'is_system' => true,
                'is_active' => true,
            ]
        );
        $floorAdmin->permissions()->sync($permissionIds);

        if ($superAdmin !== null) {
            $superAdmin->permissions()->syncWithoutDetaching($permissionIds);
        }
    }
}
