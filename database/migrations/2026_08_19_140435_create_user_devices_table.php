<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {

            $table->id();

            $table->uuid('uuid')
                ->unique();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Device Slot
            |--------------------------------------------------------------------------
            |
            | MOBILE  = Android / iOS
            | DESKTOP = Windows / macOS / Trusted Web Browser
            |
            */

            $table->string('device_slot', 20);

            /*
            |--------------------------------------------------------------------------
            | Platform
            |--------------------------------------------------------------------------
            |
            | ANDROID
            | IOS
            | WINDOWS
            | MACOS
            | WEB
            |
            */

            $table->string('platform', 20);

            /*
            |--------------------------------------------------------------------------
            | Device Information
            |--------------------------------------------------------------------------
            */

            $table->string('device_name', 150)
                ->nullable();

            $table->string('device_model', 150)
                ->nullable();

            $table->string('os_version', 100)
                ->nullable();

            $table->string('app_version', 50)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Secure Installation Identity
            |--------------------------------------------------------------------------
            |
            | Client generates random installation UUID.
            | Laravel stores only SHA-256 hash.
            |
            */

            $table->char('installation_id_hash', 64)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Device Public Key
            |--------------------------------------------------------------------------
            |
            | Private key NEVER comes to Laravel.
            |
            */

            $table->text('device_public_key')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Trust / Activity
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_trusted')
                ->default(false);

            $table->boolean('is_active')
                ->default(true);

            $table->timestamp('registered_at')
                ->nullable();

            $table->timestamp('trusted_at')
                ->nullable();

            $table->timestamp('last_seen_at')
                ->nullable();

            $table->timestamp('last_login_at')
                ->nullable();

            $table->string('last_login_ip', 45)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Revocation
            |--------------------------------------------------------------------------
            */

            $table->timestamp('revoked_at')
                ->nullable();

            $table->foreignId('revoked_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->string('revoke_reason', 500)
                ->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index([
                'user_id',
                'device_slot',
                'is_active',
            ]);

            $table->index([
                'user_id',
                'is_trusted',
            ]);

            $table->index('last_seen_at');

            $table->index('revoked_at');

            /*
             * Same installation cannot be registered
             * twice for the same user.
             */
            $table->unique(
                [
                    'user_id',
                    'installation_id_hash',
                ],
                'user_device_installation_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};