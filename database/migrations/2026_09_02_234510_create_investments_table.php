<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('investment_code', 40)->unique();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('investment_product_id')
                ->constrained('investment_products')
                ->restrictOnDelete();

            $table->string('status', 40)->index();
            $table->string('profit_model', 40)->default('PERFORMANCE_SHARE');

            $table->decimal('deposit_amount', 28, 8);
            $table->string('deposit_currency', 12)->default('USDT');
            $table->string('deposit_network', 30)->default('TRC20');

            $table->unsignedTinyInteger('investment_period_years');
            $table->unsignedTinyInteger('profit_review_interval_months');

            $table->unsignedSmallInteger('customer_profit_share_bps')->default(8000);
            $table->unsignedSmallInteger('company_profit_share_bps')->default(2000);
            $table->unsignedSmallInteger('early_exit_return_bps')->default(8000);
            $table->boolean('fixed_return_guaranteed')->default(false);

            $table->string('settlement_mode', 30)->default('ON_MATURITY');
            $table->date('custom_settlement_date')->nullable();

            $table->date('start_date')->nullable();
            $table->date('maturity_date')->nullable();
            $table->date('principal_settlement_date')->nullable();
            $table->string('principal_settlement_status', 30)->default('NOT_DUE');

            /*
             * Laravel encrypted casts expand ciphertext. TEXT avoids truncation
             * while keeping wallet snapshots encrypted at rest.
             */
            $table->text('payout_wallet_address')->nullable();
            $table->string('payout_wallet_network', 30)->default('TRC20');
            $table->timestamp('payout_wallet_verified_at')->nullable();

            $table->foreignId('payout_wallet_verified_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->decimal('high_water_mark_usdt', 28, 8)->default(0);
            $table->decimal('loss_carryforward_usdt', 28, 8)->default(0);

            $table->timestamp('activated_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->foreignId('created_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->foreignId('updated_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->foreignId('activated_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['investment_product_id', 'status']);
            $table->index('maturity_date');
            $table->index('principal_settlement_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};
