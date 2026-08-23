<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_stages', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 80);
            $table->unsignedInteger('min_qualified_sales');
            $table->unsignedInteger('max_qualified_sales')->nullable();
            $table->unsignedSmallInteger('standard_commission_bps');
            $table->unsignedInteger('milestone_sales')->nullable();
            $table->unsignedBigInteger('milestone_gift_cents')->default(0);
            $table->unsignedBigInteger('cumulative_gift_cents')->default(0);
            $table->text('commercial_rule')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index(['min_qualified_sales', 'max_qualified_sales']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_stages');
    }
};
