<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_recovery_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->char('access_token_hash', 64)->unique();
            $table->char('email_hmac', 64);
            $table->char('whatsapp_hmac', 64);

            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('whatsapp_verified_at')->nullable();
            $table->timestamp('totp_verified_at')->nullable();

            $table->char('reset_token_hash', 64)->nullable()->unique();
            $table->timestamp('reset_token_issued_at')->nullable();
            $table->timestamp('reset_token_expires_at')->nullable();

            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'expires_at'], 'password_recovery_user_expiry_idx');
            $table->index(['email_hmac', 'created_at'], 'password_recovery_email_created_idx');
            $table->index(['whatsapp_hmac', 'created_at'], 'password_recovery_whatsapp_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_recovery_sessions');
    }
};
