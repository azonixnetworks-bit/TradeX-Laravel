<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'user_registration_otp_challenges',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'user_registration_session_id'
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

                $table->foreign(
                    'user_registration_session_id',
                    'reg_otp_session_fk'
                )
                    ->references('id')
                    ->on(
                        'user_registration_sessions'
                    )
                    ->cascadeOnDelete();

                $table->index(
                    [
                        'user_registration_session_id',
                        'channel',
                        'consumed_at',
                        'invalidated_at',
                    ],
                    'registration_otp_lookup_index'
                );

                $table->index(
                    'expires_at',
                    'registration_otp_expires_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'user_registration_otp_challenges'
        );
    }
};