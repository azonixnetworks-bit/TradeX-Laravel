<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedBigInteger('lkr_amount_minor')
                ->nullable()
                ->after('amount_minor');
            $table->unsignedTinyInteger('affiliate_discount_percent')
                ->default(15)
                ->after('lkr_amount_minor');
        });

        DB::table('products')
            ->where('code', Product::CODE_EDUCATION_PREMIUM)
            ->update([
                'lkr_amount_minor' => 5000000,
                'affiliate_discount_percent' => 15,
            ]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'lkr_amount_minor',
                'affiliate_discount_percent',
            ]);
        });
    }
};
