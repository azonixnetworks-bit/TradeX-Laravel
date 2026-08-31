<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'payment_receiving_accounts',
            function (Blueprint $table): void {
                $table->string(
                    'tender_amount_decimal',
                    80
                )
                    ->nullable()
                    ->after('tender_amount_minor');

                $table->unsignedTinyInteger(
                    'amount_scale'
                )
                    ->default(2)
                    ->after('tender_amount_decimal');
            }
        );

        DB::table('payment_receiving_accounts')
            ->where('currency', 'USDT')
            ->update([
                'amount_scale' => 6,
            ]);
    }

    public function down(): void
    {
        Schema::table(
            'payment_receiving_accounts',
            function (Blueprint $table): void {
                $table->dropColumn([
                    'tender_amount_decimal',
                    'amount_scale',
                ]);
            }
        );
    }
};
