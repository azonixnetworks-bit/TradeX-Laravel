<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_orders', function (Blueprint $table): void {
            $table->foreignId('receiving_account_id')
                ->nullable()
                ->after('user_id');

            $table->string('manual_reference', 32)
                ->nullable()
                ->unique()
                ->after('provider_reference');

            $table->text('receiving_account_snapshot')
                ->nullable()
                ->after('manual_reference');

            $table->foreign('receiving_account_id', 'pay_order_receiving_account_fk')
                ->references('id')
                ->on('payment_receiving_accounts')
                ->restrictOnDelete();

            $table->index(
                ['receiving_account_id', 'status'],
                'pay_order_receiving_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('payment_orders', function (Blueprint $table): void {
            $table->dropForeign('pay_order_receiving_account_fk');
            $table->dropIndex('pay_order_receiving_status_idx');
            $table->dropColumn([
                'receiving_account_id',
                'manual_reference',
                'receiving_account_snapshot',
            ]);
        });
    }
};
