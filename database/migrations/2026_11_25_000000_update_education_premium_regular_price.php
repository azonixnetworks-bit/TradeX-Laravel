<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->where('code', Product::CODE_EDUCATION_PREMIUM)
            ->update([
                'currency' => 'USD',
                'amount_minor' => 15000,
                'updated_at' => now(),
            ]);

        DB::table('payment_receiving_accounts')
            ->where('currency', 'LKR')
            ->update([
                'tender_amount_minor' => 5000000,
                'tender_amount_decimal' => '50000.00',
                'amount_scale' => 2,
                'updated_at' => now(),
            ]);

        DB::table('payment_receiving_accounts')
            ->where('coin_symbol', 'USDT')
            ->where('network', 'TRC20')
            ->update([
                'tender_amount_minor' => 150000000,
                'tender_amount_decimal' => '150.000000',
                'amount_scale' => 6,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('products')
            ->where('code', Product::CODE_EDUCATION_PREMIUM)
            ->update([
                'currency' => 'USD',
                'amount_minor' => 7900,
                'updated_at' => now(),
            ]);
    }
};
