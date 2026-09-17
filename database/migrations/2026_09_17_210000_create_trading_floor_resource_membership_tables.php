<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trading_floor_floors', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('uuid', 'tf_floor_uuid_uq');
            $table->unique('code', 'tf_floor_code_uq');
            $table->index(['status', 'sort_order'], 'tf_floor_status_sort_idx');
            $table->foreign('created_by_admin_id', 'tf_floor_created_admin_fk')->references('id')->on('admin_users')->nullOnDelete();
            $table->foreign('updated_by_admin_id', 'tf_floor_updated_admin_fk')->references('id')->on('admin_users')->nullOnDelete();
        });

        Schema::create('trading_floor_desks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('trading_floor_floor_id');
            $table->string('code', 50);
            $table->string('name')->nullable();
            $table->string('zone', 80)->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('uuid', 'tf_desk_uuid_uq');
            $table->unique(['trading_floor_floor_id', 'code'], 'tf_desk_floor_code_uq');
            $table->index(['trading_floor_floor_id', 'status'], 'tf_desk_floor_status_idx');
            $table->foreign('trading_floor_floor_id', 'tf_desk_floor_fk')->references('id')->on('trading_floor_floors')->restrictOnDelete();
            $table->foreign('created_by_admin_id', 'tf_desk_created_admin_fk')->references('id')->on('admin_users')->nullOnDelete();
            $table->foreign('updated_by_admin_id', 'tf_desk_updated_admin_fk')->references('id')->on('admin_users')->nullOnDelete();
        });

        Schema::create('trading_floor_packages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->string('code', 80);
            $table->string('name');
            $table->text('description')->nullable();
            $table->char('currency', 3)->default('LKR');
            $table->decimal('price_amount', 18, 2)->default(0);
            $table->string('billing_cycle', 20)->default('MONTHLY');
            $table->unsignedInteger('duration_days')->nullable();
            $table->string('commission_type', 20)->nullable();
            $table->decimal('commission_rate', 8, 4)->nullable();
            $table->decimal('fixed_commission_amount', 18, 2)->nullable();
            $table->json('features')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('uuid', 'tf_package_uuid_uq');
            $table->unique('code', 'tf_package_code_uq');
            $table->index(['status', 'sort_order'], 'tf_package_status_sort_idx');
            $table->foreign('created_by_admin_id', 'tf_pkg_created_admin_fk')->references('id')->on('admin_users')->nullOnDelete();
            $table->foreign('updated_by_admin_id', 'tf_pkg_updated_admin_fk')->references('id')->on('admin_users')->nullOnDelete();
        });

        Schema::create('trading_floor_memberships', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->string('membership_number', 40);
            $table->unsignedBigInteger('trading_floor_client_id');
            $table->unsignedBigInteger('trading_floor_package_id');
            $table->unsignedBigInteger('trading_floor_floor_id');
            $table->unsignedBigInteger('trading_floor_desk_id')->nullable();
            $table->date('starts_at');
            $table->date('expires_at');
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('PENDING');
            $table->decimal('price_amount', 18, 2)->default(0);
            $table->char('currency', 3)->default('LKR');
            $table->string('billing_cycle', 20)->default('MONTHLY');
            $table->string('payment_status', 20)->default('PENDING');
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('balance_amount', 18, 2)->default(0);
            $table->boolean('auto_renew')->default(false);
            $table->unsignedBigInteger('renewed_from_membership_id')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->timestamps();

            $table->unique('uuid', 'tf_membership_uuid_uq');
            $table->unique('membership_number', 'tf_membership_number_uq');
            $table->index(['trading_floor_client_id', 'status'], 'tf_membership_client_status_idx');
            $table->index(['trading_floor_desk_id', 'status'], 'tf_membership_desk_status_idx');
            $table->index(['status', 'expires_at'], 'tf_membership_status_expiry_idx');
            $table->foreign('trading_floor_client_id', 'tf_membership_client_fk')->references('id')->on('trading_floor_clients')->restrictOnDelete();
            $table->foreign('trading_floor_package_id', 'tf_membership_package_fk')->references('id')->on('trading_floor_packages')->restrictOnDelete();
            $table->foreign('trading_floor_floor_id', 'tf_membership_floor_fk')->references('id')->on('trading_floor_floors')->restrictOnDelete();
            $table->foreign('trading_floor_desk_id', 'tf_membership_desk_fk')->references('id')->on('trading_floor_desks')->restrictOnDelete();
            $table->foreign('renewed_from_membership_id', 'tf_membership_renewed_from_fk')->references('id')->on('trading_floor_memberships')->nullOnDelete();
            $table->foreign('created_by_admin_id', 'tf_mem_created_admin_fk')->references('id')->on('admin_users')->nullOnDelete();
            $table->foreign('updated_by_admin_id', 'tf_mem_updated_admin_fk')->references('id')->on('admin_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_floor_memberships');
        Schema::dropIfExists('trading_floor_packages');
        Schema::dropIfExists('trading_floor_desks');
        Schema::dropIfExists('trading_floor_floors');
    }
};
