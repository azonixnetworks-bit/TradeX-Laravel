<?php

namespace Database\Seeders;

use App\Models\AdminPermission;
use Illuminate\Database\Seeder;

class TradingRoutinePermissionSeeder extends Seeder
{
    public function run(): void
    {
        AdminPermission::updateOrCreate(
            ['code' => 'trading_routine.view'],
            [
                'name' => 'View Trading Routine Planner',
                'module' => 'TRADING_TOOLS',
                'description' => null,
            ]
        );
    }
}
