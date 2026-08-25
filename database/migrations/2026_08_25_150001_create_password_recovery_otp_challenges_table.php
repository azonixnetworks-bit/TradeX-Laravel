<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'password_recovery_otp_challenges',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'password_recovery_session_id'
                );

                $table->string(
                    'channel',
                    20
                );

                $table->char(
                    'code_hmac',
                    64
                );

                $table->unsignedSmallInteger(
                    'attempts'
                )->default(0);

                $table->timestamp(
                    'sent_at'
                );

                $table->timestamp(
                    'expires_at'
                );

                $table->timestamp(
                    'consumed_at'
                )->nullable();

                $table->timestamp(
                    'invalidated_at'
                )->nullable();

                $table->timestamps();

                /*
                 * MySQL identifiers are limited to 64 characters.
                 * Laravel's default FK name for this long table/column
                 * combination exceeds that limit, so API-15 uses an
                 * explicit short, stable constraint name.
                 */
                $table->foreign(
                    'password_recovery_session_id',
                    'pr_otp_session_fk'
                )
                    ->references('id')
                    ->on('password_recovery_sessions')
                    ->cascadeOnDelete();

                $table->index(
                    [
                        'password_recovery_session_id',
                        'channel',
                        'sent_at',
                    ],
                    'password_recovery_otp_lookup_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'password_recovery_otp_challenges'
        );
    }
};
