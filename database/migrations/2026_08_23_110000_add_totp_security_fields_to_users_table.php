<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('totp_secret')
                ->nullable();

            $table->timestamp('totp_confirmed_at')
                ->nullable();

            $table->unsignedBigInteger('totp_last_used_step')
                ->nullable();

            $table->index(
                'totp_confirmed_at',
                'users_totp_confirmed_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(
                'users_totp_confirmed_index'
            );

            $table->dropColumn([
                'totp_secret',
                'totp_confirmed_at',
                'totp_last_used_step',
            ]);
        });
    }
};
