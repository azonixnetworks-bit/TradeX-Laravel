<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_receiving_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 20);
            $table->string('display_name', 120);
            $table->char('country_code', 2);
            $table->string('currency', 12);
            $table->string('bank_name', 120)->nullable();
            $table->string('bank_branch', 120)->nullable();
            $table->string('account_holder', 160)->nullable();
            $table->text('account_number')->nullable();
            $table->string('coin_name', 80)->nullable();
            $table->string('coin_symbol', 20)->nullable();
            $table->string('network', 60)->nullable();
            $table->text('wallet_address')->nullable();
            $table->string('qr_disk', 50)->nullable();
            $table->text('qr_path')->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('display_order')->default(100);
            $table->foreignId('created_by_admin_id')->nullable();
            $table->foreignId('updated_by_admin_id')->nullable();
            $table->timestamps();

            $table->foreign('created_by_admin_id', 'pay_recv_created_admin_fk')
                ->references('id')->on('admin_users')->nullOnDelete();
            $table->foreign('updated_by_admin_id', 'pay_recv_updated_admin_fk')
                ->references('id')->on('admin_users')->nullOnDelete();
            $table->index(
                ['type', 'country_code', 'is_active', 'display_order'],
                'pay_recv_active_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_receiving_accounts');
    }
};
