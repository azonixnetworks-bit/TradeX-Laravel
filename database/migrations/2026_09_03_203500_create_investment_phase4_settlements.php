<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_settlements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('investment_id')
                ->constrained('investments')
                ->restrictOnDelete()
                ->unique();

            $table->string('type', 30)->index();
            $table->string('status', 30)->default('DRAFT')->index();
            $table->timestamp('cutoff_at');
            $table->date('due_at');
            $table->unsignedSmallInteger('return_bps');
            $table->text('reason')->nullable();

            $table->string('calculation_version', 60)->nullable();
            $table->char('calculation_source_sha256', 64)->nullable();
            $table->json('calculation_snapshot')->nullable();

            $table->decimal('reference_capital_usdt', 28, 8)->default(0);
            $table->decimal('gross_realized_profit_usdt', 28, 8)->default(0);
            $table->decimal('realized_losses_usdt', 28, 8)->default(0);
            $table->decimal('approved_costs_usdt', 28, 8)->default(0);
            $table->decimal('loss_carryforward_open_usdt', 28, 8)->default(0);
            $table->decimal('loss_carryforward_close_usdt', 28, 8)->default(0);
            $table->decimal('eligible_net_profit_usdt', 28, 8)->default(0);
            $table->decimal('customer_profit_share_usdt', 28, 8)->default(0);
            $table->decimal('company_profit_share_usdt', 28, 8)->default(0);
            $table->decimal('rounding_adjustment_usdt', 28, 8)->default(0);

            $table->foreignId('prepared_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();
            $table->timestamp('prepared_at')->nullable();

            $table->foreignId('approved_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamp('settled_at')->nullable();

            $table->foreignId('created_by_admin_id')
                ->constrained('admin_users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index(['due_at', 'status']);
            $table->index(['type', 'status']);
        });

        Schema::create('investment_settlement_assets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('investment_settlement_id')
                ->constrained('investment_settlements')
                ->restrictOnDelete();

            $table->foreignId('investment_id')
                ->constrained('investments')
                ->restrictOnDelete();

            $table->char('asset_identity_sha256', 64);
            $table->string('asset_symbol', 30);
            $table->string('asset_network', 60)->nullable();
            $table->string('contract_address', 191)->nullable();

            $table->decimal('gross_attributable_units', 36, 18);
            $table->unsignedSmallInteger('return_bps');
            $table->decimal('customer_return_units', 36, 18);
            $table->decimal('early_exit_adjustment_units', 36, 18)->default(0);

            $table->string('network_fee_asset_symbol', 30)->nullable();
            $table->decimal('network_fee_units', 36, 18)->default(0);
            $table->boolean('network_fee_deducted_from_customer')->default(false);
            $table->decimal('net_transfer_units', 36, 18)->nullable();

            $table->json('allocation_snapshot');

            $table->string('payout_network', 60)->nullable();
            $table->text('destination_wallet')->nullable();
            $table->char('destination_wallet_sha256', 64)->nullable();
            $table->string('wallet_reference', 191)->nullable();
            $table->string('wallet_status', 30)->default('NOT_PROVIDED');

            $table->foreignId('wallet_recorded_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->foreignId('wallet_verified_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->timestamp('wallet_verified_at')->nullable();

            $table->string('status', 30)->default('AWAITING_WALLET')->index();
            $table->timestamps();

            $table->unique(
                ['investment_settlement_id', 'asset_identity_sha256'],
                'inv_settlement_asset_identity_unique'
            );
            $table->index(
                ['investment_id', 'status'],
                'inv_settlement_asset_status_idx'
            );
        });

        Schema::table('investment_transactions', function (Blueprint $table): void {
            $table->foreignId('investment_settlement_id')
                ->nullable()
                ->after('investment_profit_period_id')
                ->constrained('investment_settlements')
                ->restrictOnDelete();

            $table->foreignId('investment_settlement_asset_id')
                ->nullable()
                ->after('investment_settlement_id')
                ->constrained('investment_settlement_assets')
                ->restrictOnDelete();

            $table->index(
                ['investment_settlement_id', 'type', 'status'],
                'inv_tx_settlement_type_status_idx'
            );
            $table->index(
                ['investment_settlement_asset_id', 'status'],
                'inv_tx_settlement_asset_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('investment_transactions', function (Blueprint $table): void {
            $table->dropIndex('inv_tx_settlement_type_status_idx');
            $table->dropIndex('inv_tx_settlement_asset_status_idx');
            $table->dropForeign(['investment_settlement_asset_id']);
            $table->dropForeign(['investment_settlement_id']);
            $table->dropColumn([
                'investment_settlement_asset_id',
                'investment_settlement_id',
            ]);
        });

        Schema::dropIfExists('investment_settlement_assets');
        Schema::dropIfExists('investment_settlements');
    }
};
