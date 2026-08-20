<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_users', function (Blueprint $table) {
            $table->id();

            // Public-safe identifier.
            $table->uuid('uuid')->unique();

            $table->string('name', 150);

            $table->string('email', 190)->unique();
            $table->timestamp('email_verified_at')->nullable();

            $table->string('password');

            // Admin account state.
            $table->boolean('is_active')->default(true);

            // Login protection.
            $table->unsignedSmallInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();

            // Last successful login information.
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            $table->rememberToken();

            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('locked_until');
            $table->index('last_login_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_users');
    }
};