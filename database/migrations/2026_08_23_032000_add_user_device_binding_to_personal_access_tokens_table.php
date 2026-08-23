<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->foreignId('user_device_id')
                ->nullable()
                ->after('tokenable_id')
                ->constrained('user_devices')
                ->cascadeOnDelete();

            $table->uuid('session_uuid')
                ->nullable()
                ->after('user_device_id')
                ->index();

            $table->index(
                [
                    'user_device_id',
                    'expires_at',
                ],
                'pat_device_expiry_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex(
                'pat_device_expiry_index'
            );

            $table->dropForeign([
                'user_device_id',
            ]);

            $table->dropColumn([
                'user_device_id',
                'session_uuid',
            ]);
        });
    }
};