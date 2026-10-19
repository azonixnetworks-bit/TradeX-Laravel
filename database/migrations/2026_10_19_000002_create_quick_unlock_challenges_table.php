<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_device_quick_unlock_challenges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('quick_unlock_setting_id')
                ->constrained('user_device_quick_unlock_settings')
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
