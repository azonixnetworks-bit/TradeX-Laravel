<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copy_trade_verified_balance_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('broker_account_id');

            $table->string('currency', 10);
            $table->bigInteger('verified_billable_balance_minor');

            $table->string('source', 40);
            $table->char('source_reference_hash', 64)->nullable();
            $table->text('source_reference')->nullable();

            $table->timestamp('observed_at')->index();

            $table->foreignId('recorded_by_admin_id')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->foreign(
                'broker_account_id',
                'ct_vbb_broker_account_fk'
            )
                ->references('id')
                ->on('copy_trade_broker_accounts')
                ->restrictOnDelete();

            $table->foreign(
                'recorded_by_admin_id',
                'ct_vbb_recorded_admin_fk'
            )
                ->references('id')
                ->on('admin_users')
                ->nullOnDelete();

            $table->index(
                ['broker_account_id', 'observed_at'],
                'copy_trade_balance_account_observed_idx'
            );
        });

        Schema::create('copy_trade_account_benefits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('user_id');
            $table->foreignId('broker_account_id')->unique();
            $table->foreignId('education_entitlement_id')->nullable();

            $table->string('market_category', 20);
            $table->unsignedTinyInteger('account_slot');
            $table->unsignedInteger('cohort_position');
            $table->string('benefit_type', 40);

            $table->timestamp('assigned_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(
                'user_id',
                'ct_benefit_user_fk'
            )
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->foreign(
                'broker_account_id',
                'ct_benefit_broker_account_fk'
            )
                ->references('id')
                ->on('copy_trade_broker_accounts')
                ->restrictOnDelete();

            $table->foreign(
                'education_entitlement_id',
                'ct_benefit_education_entitlement_fk'
            )
                ->references('id')
                ->on('user_entitlements')
                ->restrictOnDelete();

            /*
             * Slot history is intentionally non-unique.
             * A disconnected account may be replaced in the same logical slot,
             * but a historical free benefit must never reset or transfer.
             */
            $table->index(
                ['user_id', 'market_category', 'account_slot'],
                'copy_trade_benefit_user_market_slot_idx'
            );

            $table->index(
                ['user_id', 'market_category'],
                'copy_trade_benefit_user_market_idx'
            );
        });

        Schema::create('copy_trade_entitlement_periods', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('broker_account_id');
            $table->foreignId('account_benefit_id')->nullable();
            $table->foreignId('user_entitlement_id')->nullable();

            $table->string('entitlement_type', 40);
            $table->string('product_code', 100);
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->nullable()->index();

            $table->timestamp('created_at')->useCurrent();

            $table->foreign(
                'broker_account_id',
                'ct_ent_period_broker_account_fk'
            )
                ->references('id')
                ->on('copy_trade_broker_accounts')
                ->restrictOnDelete();

            $table->foreign(
                'account_benefit_id',
                'ct_ent_period_benefit_fk'
            )
                ->references('id')
                ->on('copy_trade_account_benefits')
                ->restrictOnDelete();

            $table->foreign(
                'user_entitlement_id',
                'ct_ent_period_user_entitlement_fk'
            )
                ->references('id')
                ->on('user_entitlements')
                ->restrictOnDelete();

            $table->index(
                ['broker_account_id', 'starts_at'],
                'copy_trade_entitlement_account_start_idx'
            );
        });

        Schema::create('copy_trade_activation_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('broker_account_id');

            $table->string('overall_status', 20);
            $table->string('trigger_source', 30);

            $table->foreignId('evaluated_by_admin_id')->nullable();

            $table->timestamp('evaluated_at')->index();
            $table->timestamp('activated_at')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(
                'broker_account_id',
                'ct_activation_broker_account_fk'
            )
                ->references('id')
                ->on('copy_trade_broker_accounts')
                ->restrictOnDelete();

            $table->foreign(
                'evaluated_by_admin_id',
                'ct_activation_evaluated_admin_fk'
            )
                ->references('id')
                ->on('admin_users')
                ->nullOnDelete();

            $table->index(
                ['broker_account_id', 'evaluated_at'],
                'copy_trade_activation_account_evaluated_idx'
            );
        });

        Schema::create('copy_trade_activation_evaluation_gates', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('activation_evaluation_id');

            $table->string('gate_code', 60);
            $table->boolean('passed');
            $table->string('reason_code', 80);
            $table->json('evidence')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->foreign(
                'activation_evaluation_id',
                'ct_activation_gate_evaluation_fk'
            )
                ->references('id')
                ->on('copy_trade_activation_evaluations')
                ->cascadeOnDelete();

            $table->unique(
                ['activation_evaluation_id', 'gate_code'],
                'copy_trade_activation_eval_gate_uq'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copy_trade_activation_evaluation_gates');
        Schema::dropIfExists('copy_trade_activation_evaluations');
        Schema::dropIfExists('copy_trade_entitlement_periods');
        Schema::dropIfExists('copy_trade_account_benefits');
        Schema::dropIfExists('copy_trade_verified_balance_snapshots');
    }
};
