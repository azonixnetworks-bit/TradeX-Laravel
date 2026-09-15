<?php

namespace Database\Seeders;

use App\Models\AdminPermission;
use Illuminate\Database\Seeder;

class FundedAccountAdvisorPermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['funded_advisor.view', 'View Funded Account Advisor'],
            ['funded_advisor.partners.manage', 'Manage Funded Account Partners'],
        ] as [$code, $name]) {
            AdminPermission::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'module' => 'TRADING_TOOLS',
                    'description' => null,
                ]
            );
        }
    }
}
