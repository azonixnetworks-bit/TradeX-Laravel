<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payout_request_id');
            $table->foreignId('affiliate_commission_id');
            $table->unsignedBigInteger('amount_cents');
            $table->timestamps();
            $table->foreign('payout_request_id', 'payout_alloc_request_fk')->references('id')->on('payout_requests')->restrictOnDelete();
            $table->foreign('affiliate_commission_id', 'payout_alloc_commission_fk')->references('id')->on('affiliate_commissions')->restrictOnDelete();
            $table->unique(['payout_request_id', 'affiliate_commission_id'], 'payout_alloc_request_commission_unique');
            $table->index('affiliate_commission_id', 'payout_alloc_commission_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_allocations');
    }
};
