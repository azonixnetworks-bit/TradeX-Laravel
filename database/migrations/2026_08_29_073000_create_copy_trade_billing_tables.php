<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copy_trade_billing_calendar_days', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('market_category', 20);
            $table->date('calendar_date');
            $table->boolean('is_billable_day');
            $table->string('source', 40);
            $table->text('source_reference')->nullable();
            $table->foreignId('created_by_admin_id')->nullable();
            $table->timestamp('locked_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('created_by_admin_id', 'ct_bill_cal_admin_fk')
                ->references('id')->on('admin_users')->nullOnDelete();

            $table->unique(
                ['market_category', 'calendar_date'],
                'ct_bill_cal_market_date_uq'
            );
        });

        Schema::create('copy_trade_billing_cycles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('broker_account_id');
            $table->string('market_category', 20);
            $table->string('cycle_basis', 50);
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->timestamp('effective_start_at')->nullable();
            $table->string('currency', 10)->default('USD');
            $table->string('active_tier_code', 40)->nullable();
            $table->bigInteger('active_full_fee_minor')->default(0);
            $table->string('status', 30);
            $table->timestamp('grace_started_at')->nullable();
            $table->timestamp('grace_expires_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->foreign('broker_account_id', 'ct_bill_cycle_account_fk')
                ->references('id')->on('copy_trade_broker_accounts')->restrictOnDelete();

            $table->unique(
                ['broker_account_id', 'period_start', 'period_end'],
                'ct_bill_cycle_account_period_uq'
            );

            $table->index(
                ['status', 'period_end'],
                'ct_bill_cycle_status_end_idx'
            );
        });

        Schema::create('copy_trade_billing_balance_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('broker_account_id');
            $table->foreignId('balance_snapshot_id')->unique();
            $table->string('event_type', 30);
            $table->bigInteger('deposit_amount_minor');
            $table->string('source', 40);
            $table->text('source_reference')->nullable();
            $table->timestamp('occurred_at');
            $table->foreignId('recorded_by_admin_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('broker_account_id', 'ct_bill_evt_account_fk')
                ->references('id')->on('copy_trade_broker_accounts')->restrictOnDelete();

            $table->foreign('balance_snapshot_id', 'ct_bill_evt_balance_fk')
                ->references('id')->on('copy_trade_verified_balance_snapshots')->restrictOnDelete();

            $table->foreign('recorded_by_admin_id', 'ct_bill_evt_admin_fk')
                ->references('id')->on('admin_users')->nullOnDelete();

            $table->index(
                ['broker_account_id', 'occurred_at'],
                'ct_bill_evt_account_time_idx'
            );
        });

        Schema::create('copy_trade_billing_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->char('idempotency_key_hash', 64)->unique();
            $table->foreignId('broker_account_id');
            $table->foreignId('billing_cycle_id')->nullable();
            $table->foreignId('source_balance_snapshot_id');
            $table->foreignId('billing_balance_event_id')->nullable();
            $table->string('purpose', 30);
            $table->string('market_category', 20);
            $table->string('currency', 10);
            $table->bigInteger('verified_billable_balance_minor');
            $table->bigInteger('billing_eligible_balance_minor');
            $table->string('tier_code', 40);
            $table->bigInteger('full_monthly_fee_minor');
            $table->timestamp('event_at');
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->unsignedBigInteger('ratio_numerator');
            $table->unsignedBigInteger('ratio_denominator');
            $table->string('formula_code', 60);
            $table->json('calculation_inputs');
            $table->timestamp('locked_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('broker_account_id', 'ct_bill_snap_account_fk')
                ->references('id')->on('copy_trade_broker_accounts')->restrictOnDelete();

            $table->foreign('billing_cycle_id', 'ct_bill_snap_cycle_fk')
                ->references('id')->on('copy_trade_billing_cycles')->restrictOnDelete();

            $table->foreign('source_balance_snapshot_id', 'ct_bill_snap_balance_fk')
                ->references('id')->on('copy_trade_verified_balance_snapshots')->restrictOnDelete();

            $table->foreign('billing_balance_event_id', 'ct_bill_snap_event_fk')
                ->references('id')->on('copy_trade_billing_balance_events')->restrictOnDelete();

            $table->index(
                ['broker_account_id', 'event_at'],
                'ct_bill_snap_account_event_idx'
            );
        });

        Schema::create('copy_trade_billing_invoices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('invoice_number', 50)->unique();
            $table->char('idempotency_key_hash', 64)->unique();
            $table->foreignId('user_id');
            $table->foreignId('broker_account_id');
            $table->foreignId('billing_cycle_id')->nullable();
            $table->foreignId('billing_snapshot_id')->nullable();
            $table->foreignId('payment_order_id')->nullable();
            $table->foreignId('payment_id')->nullable();
            $table->string('invoice_type', 30);
            $table->string('product_code', 100);
            $table->string('currency', 10);
            $table->bigInteger('subtotal_minor');
            $table->bigInteger('total_minor');
            $table->string('status', 30);
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->timestamp('issued_at')->nullable()->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id', 'ct_bill_inv_user_fk')
                ->references('id')->on('users')->restrictOnDelete();

            $table->foreign('broker_account_id', 'ct_bill_inv_account_fk')
                ->references('id')->on('copy_trade_broker_accounts')->restrictOnDelete();

            $table->foreign('billing_cycle_id', 'ct_bill_inv_cycle_fk')
                ->references('id')->on('copy_trade_billing_cycles')->restrictOnDelete();

            $table->foreign('billing_snapshot_id', 'ct_bill_inv_snapshot_fk')
                ->references('id')->on('copy_trade_billing_snapshots')->restrictOnDelete();

            $table->foreign('payment_order_id', 'ct_bill_inv_order_fk')
                ->references('id')->on('payment_orders')->restrictOnDelete();

            $table->foreign('payment_id', 'ct_bill_inv_payment_fk')
                ->references('id')->on('payments')->restrictOnDelete();

            $table->index(
                ['broker_account_id', 'status'],
                'ct_bill_inv_account_status_idx'
            );
        });

        Schema::create('copy_trade_billing_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('billing_invoice_id');
            $table->string('line_type', 40);
            $table->string('description', 191);
            $table->bigInteger('amount_minor');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('billing_invoice_id', 'ct_bill_line_invoice_fk')
                ->references('id')->on('copy_trade_billing_invoices')->restrictOnDelete();

            $table->index(
                ['billing_invoice_id', 'line_type'],
                'ct_bill_line_invoice_type_idx'
            );
        });

        Schema::create('copy_trade_billing_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('billing_invoice_id')->nullable();
            $table->foreignId('broker_account_id');
            $table->foreignId('created_by_admin_id');
            $table->string('adjustment_type', 40);
            $table->bigInteger('amount_minor')->default(0);
            $table->text('reason');
            $table->text('evidence')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('billing_invoice_id', 'ct_bill_adj_invoice_fk')
                ->references('id')->on('copy_trade_billing_invoices')->restrictOnDelete();

            $table->foreign('broker_account_id', 'ct_bill_adj_account_fk')
                ->references('id')->on('copy_trade_broker_accounts')->restrictOnDelete();

            $table->foreign('created_by_admin_id', 'ct_bill_adj_admin_fk')
                ->references('id')->on('admin_users')->restrictOnDelete();
        });

        Schema::create('copy_trade_billing_payment_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('billing_invoice_id');
            $table->foreignId('payment_order_id')->nullable();
            $table->foreignId('payment_id')->nullable();
            $table->string('event_type', 30);
            $table->json('evidence')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('billing_invoice_id', 'ct_bill_pay_event_invoice_fk')
                ->references('id')->on('copy_trade_billing_invoices')->restrictOnDelete();

            $table->foreign('payment_order_id', 'ct_bill_pay_event_order_fk')
                ->references('id')->on('payment_orders')->restrictOnDelete();

            $table->foreign('payment_id', 'ct_bill_pay_event_payment_fk')
                ->references('id')->on('payments')->restrictOnDelete();

            $table->index(
                ['billing_invoice_id', 'event_type'],
                'ct_bill_pay_event_invoice_type_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copy_trade_billing_payment_events');
        Schema::dropIfExists('copy_trade_billing_adjustments');
        Schema::dropIfExists('copy_trade_billing_invoice_lines');
        Schema::dropIfExists('copy_trade_billing_invoices');
        Schema::dropIfExists('copy_trade_billing_snapshots');
        Schema::dropIfExists('copy_trade_billing_cycles');
        Schema::dropIfExists('copy_trade_billing_balance_events');
        Schema::dropIfExists('copy_trade_billing_calendar_days');
    }
};
