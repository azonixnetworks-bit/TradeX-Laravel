<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_asset_allocations', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('investment_id')
                ->constrained('investments')
                ->restrictOnDelete();

            $table->foreignId('source_transaction_id')
                ->nullable()
                ->constrained('investment_transactions')
                ->nullOnDelete();

            $table->string('asset_symbol', 20)->index();
            $table->string('network', 40)->nullable();
            $table->decimal('original_units', 36, 18);
            $table->decimal('remaining_units', 36, 18);
            $table->decimal('allocated_cost_usdt', 28, 8)->default(0);
            $table->timestamp('acquired_at')->nullable();
            $table->string('status', 30)->default('ACTIVE')->index();
            $table->timestamps();

            // Explicit short index name for MySQL's 64-character identifier limit.
            $table->index(
                ['investment_id', 'asset_symbol', 'status'],
                'inv_asset_alloc_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_asset_allocations');
    }
};
