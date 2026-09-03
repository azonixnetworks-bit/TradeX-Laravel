<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_products', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->string('status', 30)->index();
            $table->string('deposit_currency', 12)->nullable();
            $table->string('deposit_network', 30)->nullable();
            $table->unsignedSmallInteger('customer_profit_share_bps')->default(8000);
            $table->unsignedSmallInteger('company_profit_share_bps')->default(2000);
            $table->unsignedSmallInteger('early_exit_return_bps')->default(8000);
            $table->boolean('risk_disclosure_required')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_products');
    }
};
