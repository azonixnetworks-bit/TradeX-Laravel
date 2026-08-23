<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_qualified_sales', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('affiliate_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('referred_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('source_provider', 30)->default('PAYPAL');
            $table->string('source_reference', 191);
            $table->char('currency', 3)->default('USD');

            $table->unsignedInteger('ledger_sequence');
            $table->unsignedInteger('qualified_ordinal');
            $table->unsignedBigInteger('net_eligible_revenue_cents');

            $table->string('status', 20)->default('CLEARED');
            $table->timestamp('qualified_at');
            $table->timestamp('payment_reversed_at')->nullable();
            $table->string('payment_reversal_code', 50)->nullable();
            $table->string('payment_reversal_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(
                ['source_provider', 'source_reference'],
                'affiliate_qualified_sales_provider_reference_unique'
            );
            $table->unique(
                ['affiliate_user_id', 'ledger_sequence'],
                'affiliate_qualified_sales_ledger_sequence_unique'
            );
            $table->index(['affiliate_user_id', 'status']);
            $table->index(['referred_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_qualified_sales');
    }
};
