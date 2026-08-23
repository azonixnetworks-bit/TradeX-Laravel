<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'user_registration_sessions',
            function (Blueprint $table): void {
                $table->id();

                $table->uuid('uuid')
                    ->unique();

                $table->char(
                    'registration_token_hash',
                    64
                )->unique();

                $table->string('name', 120);
                $table->string('email', 255);
                $table->string(
                    'whatsapp_number',
                    30
                );

                $table->string(
                    'password_hash',
                    255
                );

                $table->string(
                    'referral_code_input',
                    30
                )->nullable();

                $table->foreignId(
                    'referred_by_user_id'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->char(
                    'country_code',
                    2
                );

                $table->string(
                    'language_code',
                    10
                )->default('en');

                $table->timestamp(
                    'email_verified_at'
                )->nullable();

                $table->timestamp(
                    'whatsapp_verified_at'
                )->nullable();

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

                $table->string(
                    'status',
                    20
                )->default('PENDING');

                $table->timestamp(
                    'expires_at'
                );

                $table->foreignId(
                    'completed_user_id'
                )
                    ->nullable()
                    ->unique()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp(
                    'completed_at'
                )->nullable();

                $table->string(
                    'start_ip',
                    45
                )->nullable();

                $table->text(
                    'start_user_agent'
                )->nullable();

                $table->timestamps();

                $table->index([
                    'email',
                    'status',
                ]);

                $table->index([
                    'whatsapp_number',
                    'status',
                ]);

                $table->index('expires_at');
                $table->index('status');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'user_registration_sessions'
        );
    }
};
