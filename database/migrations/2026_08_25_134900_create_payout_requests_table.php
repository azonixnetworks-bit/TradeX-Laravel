<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('method', 32);
            $table->string('status', 32)->default('PENDING_REVIEW');
            $table->unsignedBigInteger('amount_cents');
            $table->char('currency', 3)->default('USD');
            $table->longText('destination_snapshot');
            $table->char('destination_fingerprint', 64);
            $table->char('idempotency_key_hash', 64)->unique();
            $table->text('provider_reference')->nullable();
            $table->text('user_visible_reason')->nullable();
            $table->longText('internal_notes')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'status'], 'payout_requests_user_status_idx');
            $table->index(['user_id', 'requested_at'], 'payout_requests_user_requested_idx');
            $table->index(['method', 'status'], 'payout_requests_method_status_idx');
            $table->index('destination_fingerprint', 'payout_requests_destination_fp_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_requests');
    }
};
