<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A previous interrupted release can create the table before Laravel
        // records this migration. Treat that state as already migrated so a
        // retry is safe and does not fail with "table already exists".
        if (Schema::hasTable('user_device_quick_unlock_challenges')) {
            return;
        }

        Schema::create('user_device_quick_unlock_challenges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('quick_unlock_setting_id')
                // cPanel's MySQL enforces the 64-character identifier limit.
                // Laravel's generated foreign-key name for these two long table
                // names exceeds it, so keep the database constraint explicit.
                ->constrained(
                    'user_device_quick_unlock_settings',
                    'id',
                    'quick_unlock_challenge_setting_fk'
                )
                ->cascadeOnDelete();
            $table->char('nonce_hash', 64);
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(
                ['quick_unlock_setting_id', 'expires_at'],
                'quick_unlock_challenge_setting_expiry'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_device_quick_unlock_challenges');
    }
};
