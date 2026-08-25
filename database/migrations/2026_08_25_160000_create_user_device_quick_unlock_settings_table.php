<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'user_device_quick_unlock_settings',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'user_device_id'
                );

                $table->boolean(
                    'enabled'
                )->default(false);

                $table->string(
                    'method',
                    20
                )->nullable();

                /*
                 * Snapshot of the device trust epoch at enrollment time.
                 * Password recovery clears trusted_at; the next strong login
                 * creates a new trust epoch. Old quick-unlock enrollment then
                 * becomes stale automatically without storing any PIN/biometric.
                 */
                $table->timestamp(
                    'trusted_at_snapshot'
                )->nullable();

                $table->timestamp(
                    'enrolled_at'
                )->nullable();

                $table->timestamp(
                    'disabled_at'
                )->nullable();

                $table->timestamps();

                $table->unique(
                    'user_device_id',
                    'ud_quick_unlock_device_unique'
                );

                $table->foreign(
                    'user_device_id',
                    'ud_quick_unlock_device_fk'
                )
                    ->references('id')
                    ->on('user_devices')
                    ->cascadeOnDelete();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'user_device_quick_unlock_settings'
        );
    }
};
