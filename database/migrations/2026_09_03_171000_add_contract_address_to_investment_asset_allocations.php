<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investment_asset_allocations', function (Blueprint $table): void {
            $table->string('contract_address', 191)
                ->nullable()
                ->after('network');

            $table->index(
                [
                    'investment_id',
                    'asset_symbol',
                    'network',
                    'contract_address',
                    'status',
                ],
                'inv_asset_identity_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('investment_asset_allocations', function (Blueprint $table): void {
            $table->dropIndex('inv_asset_identity_status_idx');
            $table->dropColumn('contract_address');
        });
    }
};
