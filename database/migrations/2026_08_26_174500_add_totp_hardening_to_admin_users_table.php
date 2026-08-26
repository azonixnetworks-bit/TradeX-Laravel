<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'admin_users',
            function (Blueprint $table): void {
                $table->text(
                    'totp_secret'
                )->nullable();

                $table->timestamp(
                    'totp_setup_at'
                )->nullable();

                $table->timestamp(
                    'totp_confirmed_at'
                )->nullable();

                $table->unsignedBigInteger(
                    'totp_last_used_step'
                )->nullable();

                $table->index(
                    'totp_confirmed_at',
                    'admin_totp_confirmed_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'admin_users',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'admin_totp_confirmed_idx'
                );

                $table->dropColumn([
                    'totp_secret',
                    'totp_setup_at',
                    'totp_confirmed_at',
                    'totp_last_used_step',
                ]);
            }
        );
    }
};
