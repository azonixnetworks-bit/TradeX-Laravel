<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_commissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('affiliate_qualified_sale_id')
                ->unique()
                ->constrained('affiliate_qualified_sales')
                ->restrictOnDelete();
            $table->foreignId('affiliate_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->unsignedInteger('qualified_ordinal');
            $table->unsignedSmallInteger('rate_bps');
            $table->string('rate_source', 20);
            $table->unsignedBigInteger('calculation_base_cents');
            $table->unsignedBigInteger('commission_amount_cents');
            $table->char('currency', 3)->default('USD');
            $table->string('status', 20)->default('EARNED');
            $table->timestamp('earned_at');
            $table->timestamps();

            $table->index(['affiliate_user_id', 'earned_at']);
            $table->index(['affiliate_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_commissions');
    }
};
