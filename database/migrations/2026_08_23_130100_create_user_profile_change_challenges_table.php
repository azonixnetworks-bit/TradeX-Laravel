<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'user_profile_change_challenges',
            function (Blueprint $table): void {
                $table->id();

                $table->uuid('uuid')
                    ->unique();

                $table->unsignedBigInteger(
                    'user_id'
                );

                $table->string(
                    'purpose',
                    40
                );

                $table->char(
                    'challenge_token_hash',
                    64
                )->unique();

                $table->text(
                    'new_value'
                );

                $table->char(
                    'otp_hmac',
                    64
                );

                $table->unsignedSmallInteger(
                    'attempts'
                )->default(0);

                $table->timestamp(
                    'expires_at'
                );

                $table->timestamp(
                    'consumed_at'
                )->nullable();

                $table->timestamp(
                    'invalidated_at'
                )->nullable();

                $table->string(
                    'request_ip',
                    45
                )->nullable();

                $table->text(
                    'request_user_agent'
                )->nullable();

                $table->timestamps();

                $table->foreign(
                    'user_id',
                    'profile_change_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();

                $table->index(
                    [
                        'user_id',
                        'purpose',
                        'consumed_at',
                        'invalidated_at',
                    ],
                    'profile_change_lookup_idx'
                );

                $table->index(
                    'expires_at',
                    'profile_change_expiry_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'user_profile_change_challenges'
        );
    }
};
