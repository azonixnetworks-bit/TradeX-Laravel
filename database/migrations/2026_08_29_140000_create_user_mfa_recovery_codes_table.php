<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'user_mfa_recovery_codes',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->foreignId('user_id');
                $table->char('code_hash', 64)->unique();

                $table->timestamp('issued_at');
                $table->timestamp('used_at')->nullable();
                $table->timestamp('revoked_at')->nullable();

                $table->string('used_ip', 45)->nullable();
                $table->string('used_user_agent', 1024)->nullable();

                $table->timestamps();

                $table->foreign(
                    'user_id',
                    'mfa_recovery_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();

                $table->index(
                    [
                        'user_id',
                        'used_at',
                        'revoked_at',
                    ],
                    'mfa_recovery_user_state_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'user_mfa_recovery_codes'
        );
    }
};
