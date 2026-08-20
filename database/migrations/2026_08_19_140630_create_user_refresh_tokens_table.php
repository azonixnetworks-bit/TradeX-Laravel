<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_refresh_tokens', function (Blueprint $table) {

            $table->id();

            $table->uuid('uuid')
                ->unique();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('user_device_id')
                ->constrained('user_devices')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Token Security
            |--------------------------------------------------------------------------
            |
            | Plain refresh token must NOT be stored.
            | Store SHA-256 digest only.
            |
            */

            $table->char('token_hash', 64)
                ->unique();

            /*
             * Used later for refresh-token rotation family.
             */
            $table->uuid('family_uuid')
                ->index();

            /*
            |--------------------------------------------------------------------------
            | Lifetime
            |--------------------------------------------------------------------------
            */

            $table->timestamp('expires_at');

            $table->timestamp('last_used_at')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Revocation
            |--------------------------------------------------------------------------
            */

            $table->timestamp('revoked_at')
                ->nullable();

            $table->string('revoke_reason', 500)
                ->nullable();

            $table->timestamps();

            $table->index([
                'user_id',
                'revoked_at',
            ]);

            $table->index([
                'user_device_id',
                'revoked_at',
            ]);

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'user_refresh_tokens'
        );
    }
};