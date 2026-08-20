<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            /*
             * Who performed the action?
             * ADMIN / SYSTEM / USER
             */
            $table->string('actor_type', 30);

            $table->unsignedBigInteger('actor_id')->nullable();

            /*
             * Example:
             * ADMIN_LOGIN
             * USER_SUSPENDED
             * PAYMENT_APPROVED
             */
            $table->string('action', 120);

            /*
             * Example:
             * AUTH
             * USERS
             * PAYMENTS
             * KYC
             */
            $table->string('module', 100)->nullable();

            /*
             * What entity was affected?
             *
             * Example:
             * User
             * Payment
             * PayoutRequest
             */
            $table->string('target_type', 120)->nullable();

            $table->unsignedBigInteger('target_id')->nullable();

            // Before / after state where required.
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Request metadata.
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index([
                'actor_type',
                'actor_id',
            ]);

            $table->index([
                'target_type',
                'target_id',
            ]);

            $table->index('action');
            $table->index('module');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};