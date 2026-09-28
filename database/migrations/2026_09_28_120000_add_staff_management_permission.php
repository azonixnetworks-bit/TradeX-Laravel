<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('admin_permissions')->updateOrInsert(
            ['code' => 'staff.manage'],
            [
                'name' => 'Manage Staff',
                'module' => 'STAFF',
                'description' => 'Create and manage operational staff, KYC, leave, payroll and payout approvals.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('admin_permissions')->where('code', 'staff.manage')->delete();
    }
};

