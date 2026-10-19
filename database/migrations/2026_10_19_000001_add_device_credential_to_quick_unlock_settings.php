<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_device_quick_unlock_settings', function (Blueprint $table): void {
            $table->uuid('credential_id')->nullable();
            $table->string('credential_algorithm', 20)->nullable();
            $table->text('credential_public_key')->nullable();
            $table->timestamp('credential_rotated_at')->nullable();
            $table->unique('credential_id', 'ud_quick_unlock_credential_unique');
        });
    }

    public function down(): void
    {
        Schema::table('user_device_quick_unlock_settings', function (Blueprint $table): void {
            $table->dropUnique('ud_quick_unlock_credential_unique');
            $table->dropColumn([
                'credential_id',
                'credential_algorithm',
                'credential_public_key',
                'credential_rotated_at',
            ]);
        });
    }
};
