<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('admin_user_id')
                ->constrained('admin_users')
                ->cascadeOnDelete();
            $table->string('session_id', 255)->nullable()->index();
            $table->string('device_name', 120)->nullable();
            $table->string('device_type', 40)->nullable();
            $table->string('browser', 80)->nullable();
            $table->string('platform', 80)->nullable();
            $table->string('ip_address', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->timestamp('login_at');
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('logged_out_at')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->string('revoked_reason', 80)->nullable();
            $table->timestamps();

            $table->index(
                ['admin_user_id', 'revoked_at', 'logged_out_at'],
                'admin_sessions_active_lookup'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_sessions');
    }
};
