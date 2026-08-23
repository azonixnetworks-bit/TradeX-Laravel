<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'user_entitlements',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->foreignId('user_id');

                $table->string(
                    'product_code',
                    50
                );

                $table->string(
                    'status',
                    30
                )->default('ACTIVE');

                $table->timestamp(
                    'activated_at'
                );

                $table->foreignId(
                    'payment_id'
                )->nullable();

                $table->timestamp(
                    'revoked_at'
                )->nullable();

                $table->text(
                    'revoke_reason'
                )->nullable();

                $table->timestamps();

                $table->foreign(
                    'user_id',
                    'usr_ent_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->restrictOnDelete();

                $table->foreign(
                    'payment_id',
                    'usr_ent_payment_fk'
                )
                    ->references('id')
                    ->on('payments')
                    ->nullOnDelete();

                $table->unique(
                    [
                        'user_id',
                        'product_code',
                    ],
                    'usr_ent_user_product_uq'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'user_entitlements'
        );
    }
};
