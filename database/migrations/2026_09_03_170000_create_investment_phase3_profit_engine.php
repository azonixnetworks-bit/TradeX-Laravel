<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_trades', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('investment_id')
                ->constrained('investments')
                ->restrictOnDelete();
            $table->foreignId('investment_profit_period_id')
                ->nullable()
                ->constrained('investment_profit_periods')
                ->restrictOnDelete();
            $table->string('action', 20)->index();
            $table->string('instrument_type', 20)->default('SPOT');
            $table->string('asset_name', 120);
            $table->string('asset_symbol', 30)->index();
            $table->string('network', 60)->nullable();
            $table->string('contract_address', 191)->nullable();
            $table->string('exchange_broker', 120);
            $table->decimal('quantity', 36, 18);
            $table->decimal('executed_price_usdt', 28, 8);
            $table->decimal('gross_value_usdt', 28, 8);
            $table->decimal('fee_usdt', 28, 8)->default(0);
            $table->decimal('realized_pnl_usdt', 28, 8)->default(0);
            $table->string('order_reference', 191)->nullable();
            $table->timestamp('executed_at');
            $table->string('status', 30)->default('PENDING_VERIFICATION')->index();
            $table->foreignId('recorded_by_admin_id')
                ->constrained('admin_users')
                ->restrictOnDelete();
            $table->foreignId('verified_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(
                ['investment_id', 'investment_profit_period_id', 'status'],
                'inv_trade_period_status_idx'
            );
            $table->index(
                ['investment_id', 'executed_at'],
                'inv_trade_time_idx'
            );
        });

        Schema::create('investment_approved_costs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('investment_id')
                ->constrained('investments')
                ->restrictOnDelete();
            $table->foreignId('investment_profit_period_id')
                ->constrained('investment_profit_periods')
                ->restrictOnDelete();
            $table->foreignId('investment_trade_id')
                ->nullable()
                ->constrained('investment_trades')
                ->restrictOnDelete();
            $table->string('cost_type', 60)->index();
            $table->decimal('amount_usdt', 28, 8);
            $table->timestamp('occurred_at');
            $table->string('source_reference', 191);
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('PENDING_VERIFICATION')->index();
            $table->foreignId('recorded_by_admin_id')
                ->constrained('admin_users')
                ->restrictOnDelete();
            $table->foreignId('verified_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(
                ['investment_id', 'investment_profit_period_id', 'status'],
                'inv_cost_period_status_idx'
            );
        });

        Schema::create('investment_financial_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('investment_id')
                ->constrained('investments')
                ->restrictOnDelete();
            $table->foreignId('investment_profit_period_id');
            $table->foreign(
                'investment_profit_period_id',
                'inv_fin_adj_period_fk'
            )->references('id')->on('investment_profit_periods')->restrictOnDelete();
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id');
            $table->string('adjustment_type', 20);
            $table->decimal('realized_pnl_delta_usdt', 28, 8)->default(0);
            $table->decimal('approved_cost_delta_usdt', 28, 8)->default(0);
            $table->text('reason');
            $table->string('status', 30)->default('PENDING_VERIFICATION')->index();
            $table->foreignId('recorded_by_admin_id')
                ->constrained('admin_users')
                ->restrictOnDelete();
            $table->foreignId('verified_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(
                ['target_type', 'target_id', 'status'],
                'inv_adjust_target_status_idx'
            );
            $table->index(
                ['investment_id', 'investment_profit_period_id', 'status'],
                'inv_adjust_period_status_idx'
            );
        });

        Schema::create('investment_evidences', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('investment_id')
                ->constrained('investments')
                ->restrictOnDelete();
            $table->foreignId('investment_profit_period_id')
                ->nullable()
                ->constrained('investment_profit_periods')
                ->restrictOnDelete();
            $table->foreignId('investment_trade_id')
                ->nullable()
                ->constrained('investment_trades')
                ->restrictOnDelete();
            $table->foreignId('investment_approved_cost_id')
                ->nullable()
                ->constrained('investment_approved_costs')
                ->restrictOnDelete();
            $table->string('evidence_type', 40)->index();
            $table->string('account_reference', 191)->nullable();
            $table->string('source_reference', 191)->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('document_disk', 40);
            $table->text('document_path');
            $table->string('document_mime_type', 120);
            $table->unsignedBigInteger('document_size_bytes');
            $table->char('document_sha256', 64);
            $table->string('status', 30)->default('PENDING_VERIFICATION')->index();
            $table->foreignId('uploaded_by_admin_id')
                ->constrained('admin_users')
                ->restrictOnDelete();
            $table->foreignId('verified_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(
                ['investment_id', 'investment_profit_period_id', 'status'],
                'inv_evidence_period_status_idx'
            );
        });

        Schema::table('investment_asset_allocations', function (Blueprint $table): void {
            $table->foreignId('source_trade_id')
                ->nullable()
                ->after('source_transaction_id')
                ->constrained('investment_trades')
                ->restrictOnDelete();
        });

        Schema::create('investment_asset_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('investment_id')
                ->constrained('investments')
                ->restrictOnDelete();
            $table->foreignId('investment_trade_id')
                ->nullable()
                ->constrained('investment_trades')
                ->restrictOnDelete();
            $table->foreignId('investment_financial_adjustment_id')->nullable();
            $table->foreign(
                'investment_financial_adjustment_id',
                'inv_asset_move_adjust_fk'
            )->references('id')->on('investment_financial_adjustments')->restrictOnDelete();
            $table->foreignId('investment_asset_allocation_id');
            $table->foreign(
                'investment_asset_allocation_id',
                'inv_asset_move_alloc_fk'
            )->references('id')->on('investment_asset_allocations')->restrictOnDelete();
            $table->string('movement_type', 20);
            $table->decimal('units_delta', 36, 18);
            $table->foreignId('created_by_admin_id')
                ->constrained('admin_users')
                ->restrictOnDelete();
            $table->timestamps();

            $table->index(
                ['investment_id', 'investment_asset_allocation_id'],
                'inv_asset_move_lookup_idx'
            );
        });

        Schema::table('investment_profit_periods', function (Blueprint $table): void {
            $table->string('distribution_outcome', 30)
                ->nullable()
                ->after('status')
                ->index();
            $table->decimal('rounding_adjustment_usdt', 28, 8)
                ->default(0)
                ->after('company_profit_share_usdt');
            $table->string('calculation_version', 40)
                ->nullable()
                ->after('rounding_adjustment_usdt');
            $table->char('calculation_source_sha256', 64)
                ->nullable()
                ->after('calculation_version');
            $table->json('calculation_snapshot')
                ->nullable()
                ->after('calculation_source_sha256');
        });
    }

    public function down(): void
    {
        Schema::table('investment_profit_periods', function (Blueprint $table): void {
            $table->dropIndex(['distribution_outcome']);
            $table->dropColumn([
                'distribution_outcome',
                'rounding_adjustment_usdt',
                'calculation_version',
                'calculation_source_sha256',
                'calculation_snapshot',
            ]);
        });

        Schema::dropIfExists('investment_asset_movements');

        Schema::table('investment_asset_allocations', function (Blueprint $table): void {
            $table->dropForeign(['source_trade_id']);
            $table->dropColumn('source_trade_id');
        });

        Schema::dropIfExists('investment_evidences');
        Schema::dropIfExists('investment_financial_adjustments');
        Schema::dropIfExists('investment_approved_costs');
        Schema::dropIfExists('investment_trades');
    }
};
