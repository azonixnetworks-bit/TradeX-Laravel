<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trading_floor_clients', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('tf_clients_uuid_uq');
            $table->string('client_code', 32)->unique('tf_clients_code_uq');
            $table->foreignId('user_id')->nullable();
            $table->foreign('user_id', 'tf_clients_user_fk')->references('id')->on('users')->nullOnDelete();
            $table->string('full_name');
            $table->string('mobile', 40)->nullable();
            $table->string('whatsapp_number', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('identity_type', 20)->nullable();
            $table->string('identity_number', 120)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 40)->nullable();
            $table->date('joined_at')->nullable();
            $table->string('floor_code', 50)->nullable();
            $table->string('desk_code', 50)->nullable();
            $table->string('package_code', 80)->nullable();
            $table->string('commission_type', 30)->default('PERCENTAGE');
            $table->decimal('commission_rate', 8, 4)->nullable();
            $table->decimal('fixed_commission_amount', 18, 2)->nullable();
            $table->string('commission_currency', 3)->default('LKR');
            $table->string('billing_cycle', 30)->default('MONTHLY');
            $table->unsignedSmallInteger('grace_days')->default(0);
            $table->string('status', 30)->default('ACTIVE');
            $table->foreignId('created_by_admin_id')->nullable();
            $table->foreign('created_by_admin_id', 'tf_clients_created_admin_fk')->references('id')->on('admin_users')->nullOnDelete();
            $table->foreignId('updated_by_admin_id')->nullable();
            $table->foreign('updated_by_admin_id', 'tf_clients_updated_admin_fk')->references('id')->on('admin_users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'joined_at'], 'tf_clients_status_join_idx');
        });

        Schema::create('trading_floor_access_credentials', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('tf_access_cred_uuid_uq');
            $table->foreignId('trading_floor_client_id');
            $table->foreign('trading_floor_client_id', 'tf_access_cred_client_fk')->references('id')->on('trading_floor_clients')->cascadeOnDelete();
            $table->string('credential_type', 30);
            $table->string('credential_reference', 255);
            $table->string('device_code', 100)->nullable();
            $table->string('status', 30)->default('ACTIVE');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->foreignId('created_by_admin_id')->nullable();
            $table->foreign('created_by_admin_id', 'tf_access_cred_admin_fk')->references('id')->on('admin_users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['credential_type', 'credential_reference'], 'tf_access_cred_type_ref_uq');
            $table->index(['trading_floor_client_id', 'status'], 'tf_access_cred_client_status_idx');
        });

        Schema::create('trading_floor_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('tf_access_logs_uuid_uq');
            $table->foreignId('trading_floor_client_id');
            $table->foreign('trading_floor_client_id', 'tf_access_logs_client_fk')->references('id')->on('trading_floor_clients')->cascadeOnDelete();
            $table->foreignId('trading_floor_access_credential_id')->nullable();
            $table->foreign('trading_floor_access_credential_id', 'tf_access_logs_cred_fk')->references('id')->on('trading_floor_access_credentials')->nullOnDelete();
            $table->string('direction', 20);
            $table->string('method', 30);
            $table->string('device_code', 100)->nullable();
            $table->string('decision', 20)->default('GRANTED');
            $table->string('decision_reason', 255)->nullable();
            $table->timestamp('accessed_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['accessed_at', 'direction'], 'tf_access_logs_time_dir_idx');
            $table->index(['trading_floor_client_id', 'accessed_at'], 'tf_access_logs_client_time_idx');
        });

        Schema::create('trading_floor_commission_invoices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('tf_comm_inv_uuid_uq');
            $table->string('invoice_number', 50)->unique('tf_comm_inv_number_uq');
            $table->foreignId('trading_floor_client_id');
            $table->foreign('trading_floor_client_id', 'tf_comm_inv_client_fk')->references('id')->on('trading_floor_clients')->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date');
            $table->string('commission_type', 30);
            $table->decimal('trading_profit_amount', 18, 2)->nullable();
            $table->decimal('commission_rate', 8, 4)->nullable();
            $table->decimal('commission_amount', 18, 2);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('balance_amount', 18, 2);
            $table->string('currency', 3)->default('LKR');
            $table->string('status', 30)->default('PENDING');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_admin_id')->nullable();
            $table->foreign('created_by_admin_id', 'tf_comm_inv_created_admin_fk')->references('id')->on('admin_users')->nullOnDelete();
            $table->foreignId('updated_by_admin_id')->nullable();
            $table->foreign('updated_by_admin_id', 'tf_comm_inv_updated_admin_fk')->references('id')->on('admin_users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'due_date'], 'tf_comm_inv_status_due_idx');
            $table->index(['trading_floor_client_id', 'period_start', 'period_end'], 'tf_comm_inv_client_period_idx');
        });

        Schema::create('trading_floor_commission_payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('tf_comm_pay_uuid_uq');
            $table->foreignId('trading_floor_commission_invoice_id');
            $table->foreign('trading_floor_commission_invoice_id', 'tf_comm_pay_invoice_fk')->references('id')->on('trading_floor_commission_invoices')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->string('payment_method', 50);
            $table->string('transaction_reference', 190)->nullable();
            $table->string('proof_path')->nullable();
            $table->timestamp('received_at');
            $table->foreignId('received_by_admin_id')->nullable();
            $table->foreign('received_by_admin_id', 'tf_comm_pay_received_admin_fk')->references('id')->on('admin_users')->nullOnDelete();
            $table->string('status', 30)->default('RECEIVED');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['received_at', 'status'], 'tf_comm_pay_received_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_floor_commission_payments');
        Schema::dropIfExists('trading_floor_commission_invoices');
        Schema::dropIfExists('trading_floor_access_logs');
        Schema::dropIfExists('trading_floor_access_credentials');
        Schema::dropIfExists('trading_floor_clients');
    }
};
