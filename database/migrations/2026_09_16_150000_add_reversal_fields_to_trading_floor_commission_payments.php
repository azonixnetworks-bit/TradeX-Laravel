<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trading_floor_commission_payments', function (Blueprint $table): void {
            $table->timestamp('reversed_at')->nullable()->after('status');
            $table->foreignId('reversed_by_admin_id')->nullable()->after('reversed_at');
            $table->text('reversal_reason')->nullable()->after('reversed_by_admin_id');

            $table->foreign('reversed_by_admin_id', 'tfcp_reversed_by_fk')
                ->references('id')
                ->on('admin_users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trading_floor_commission_payments', function (Blueprint $table): void {
            $table->dropForeign('tfcp_reversed_by_fk');
            $table->dropColumn([
                'reversed_at',
                'reversed_by_admin_id',
                'reversal_reason',
            ]);
        });
    }
};
