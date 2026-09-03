<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('investment_id')
                ->constrained('investments')
                ->restrictOnDelete();

            $table->foreignId('investment_profit_period_id')
                ->nullable()
                ->constrained('investment_profit_periods')
                ->nullOnDelete();

            $table->string('direction', 10);
            $table->string('type', 40)->index();
            $table->string('asset_symbol', 20);
            $table->decimal('amount', 36, 18);
            $table->decimal('usdt_equivalent', 28, 8)->nullable();
            $table->string('network', 40)->nullable();

            $table->text('from_address')->nullable();
            $table->text('to_address')->nullable();

            $table->string('txid', 190)->nullable();
            $table->string('status', 30)->default('PENDING')->index();
            $table->timestamp('occurred_at')->nullable();

            $table->foreignId('recorded_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->foreignId('verified_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['network', 'txid'],
                'investment_transactions_network_txid_unique'
            );

            $table->index(['investment_id', 'direction', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_transactions');
    }
};
