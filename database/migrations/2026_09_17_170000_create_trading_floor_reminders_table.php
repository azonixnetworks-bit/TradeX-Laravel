<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trading_floor_reminders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('trading_floor_client_id');
            $table->unsignedBigInteger('trading_floor_commission_invoice_id')->nullable();
            $table->unsignedBigInteger('trading_floor_commission_payment_id')->nullable();
            $table->string('reminder_type', 40);
            $table->string('channel', 20)->default('IN_APP');
            $table->string('recipient')->nullable();
            $table->char('dedupe_key', 64);
            $table->string('status', 20)->default('PENDING');
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('provider_reference')->nullable();
            $table->json('payload')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->timestamps();

            $table->unique('uuid', 'tf_reminder_uuid_uq');
            $table->unique('dedupe_key', 'tf_reminder_dedupe_uq');
            $table->index(['status', 'scheduled_for'], 'tf_reminder_status_sched_idx');
            $table->index(['trading_floor_client_id', 'created_at'], 'tf_reminder_client_time_idx');
            $table->foreign('trading_floor_client_id', 'tf_reminder_client_fk')
                ->references('id')->on('trading_floor_clients')->restrictOnDelete();
            $table->foreign('trading_floor_commission_invoice_id', 'tf_reminder_invoice_fk')
                ->references('id')->on('trading_floor_commission_invoices')->restrictOnDelete();
            $table->foreign('trading_floor_commission_payment_id', 'tf_reminder_payment_fk')
                ->references('id')->on('trading_floor_commission_payments')->restrictOnDelete();
            $table->foreign('created_by_admin_id', 'tf_reminder_admin_fk')
                ->references('id')->on('admin_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_floor_reminders');
    }
};
