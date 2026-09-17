<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trading_floor_membership_payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('trading_floor_membership_id');
            $table->decimal('amount', 18, 2);
            $table->char('currency', 3);
            $table->string('payment_method', 40);
            $table->string('transaction_reference', 190)->nullable();
            $table->timestamp('received_at');
            $table->unsignedBigInteger('received_by_admin_id')->nullable();
            $table->string('status', 20)->default('RECEIVED');
            $table->text('notes')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->unsignedBigInteger('reversed_by_admin_id')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->unique('uuid', 'tf_mem_pay_uuid_uq');
            $table->index(['trading_floor_membership_id', 'status'], 'tf_mem_pay_membership_status_idx');
            $table->index('received_at', 'tf_mem_pay_received_idx');
            $table->foreign('trading_floor_membership_id', 'tf_mem_pay_membership_fk')
                ->references('id')->on('trading_floor_memberships')->restrictOnDelete();
            $table->foreign('received_by_admin_id', 'tf_mem_pay_received_admin_fk')
                ->references('id')->on('admin_users')->nullOnDelete();
            $table->foreign('reversed_by_admin_id', 'tf_mem_pay_reversed_admin_fk')
                ->references('id')->on('admin_users')->nullOnDelete();
        });

        Schema::create('trading_floor_membership_changes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('trading_floor_membership_id');
            $table->string('change_type', 40);
            $table->unsignedBigInteger('old_package_id')->nullable();
            $table->unsignedBigInteger('new_package_id')->nullable();
            $table->unsignedBigInteger('old_floor_id')->nullable();
            $table->unsignedBigInteger('new_floor_id')->nullable();
            $table->unsignedBigInteger('old_desk_id')->nullable();
            $table->unsignedBigInteger('new_desk_id')->nullable();
            $table->date('old_expires_at')->nullable();
            $table->date('new_expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('changed_by_admin_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique('uuid', 'tf_mem_change_uuid_uq');
            $table->index(['trading_floor_membership_id', 'occurred_at'], 'tf_mem_change_membership_time_idx');
            $table->foreign('trading_floor_membership_id', 'tf_mem_change_membership_fk')
                ->references('id')->on('trading_floor_memberships')->restrictOnDelete();
            $table->foreign('old_package_id', 'tf_mem_change_old_pkg_fk')->references('id')->on('trading_floor_packages')->nullOnDelete();
            $table->foreign('new_package_id', 'tf_mem_change_new_pkg_fk')->references('id')->on('trading_floor_packages')->nullOnDelete();
            $table->foreign('old_floor_id', 'tf_mem_change_old_floor_fk')->references('id')->on('trading_floor_floors')->nullOnDelete();
            $table->foreign('new_floor_id', 'tf_mem_change_new_floor_fk')->references('id')->on('trading_floor_floors')->nullOnDelete();
            $table->foreign('old_desk_id', 'tf_mem_change_old_desk_fk')->references('id')->on('trading_floor_desks')->nullOnDelete();
            $table->foreign('new_desk_id', 'tf_mem_change_new_desk_fk')->references('id')->on('trading_floor_desks')->nullOnDelete();
            $table->foreign('changed_by_admin_id', 'tf_mem_change_admin_fk')->references('id')->on('admin_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_floor_membership_changes');
        Schema::dropIfExists('trading_floor_membership_payments');
    }
};
