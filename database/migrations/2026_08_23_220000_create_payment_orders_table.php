<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'payment_orders',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->foreignId('user_id');

                $table->string('product_code', 50);
                $table->string('provider', 30);
                $table->char('country_code', 2);
                $table->string('currency', 8);
                $table->unsignedBigInteger('amount_minor');

                $table->string('status', 30)
                    ->default('CREATED');

                $table->char(
                    'client_idempotency_key_hash',
                    64
                );

                $table->string(
                    'provider_reference',
                    191
                )->nullable();

                $table->text(
                    'approval_url'
                )->nullable();

                $table->string(
                    'failure_code',
                    80
                )->nullable();

                $table->timestamp(
                    'cleared_at'
                )->nullable();

                $table->timestamp(
                    'expires_at'
                )->nullable();

                $table->timestamps();

                $table->foreign(
                    'user_id',
                    'pay_order_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->restrictOnDelete();

                $table->unique(
                    [
                        'user_id',
                        'client_idempotency_key_hash',
                    ],
                    'pay_order_user_idem_uq'
                );

                $table->unique(
                    [
                        'provider',
                        'provider_reference',
                    ],
                    'pay_order_provider_ref_uq'
                );

                $table->index(
                    [
                        'user_id',
                        'status',
                        'created_at',
                    ],
                    'pay_order_user_status_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'payment_orders'
        );
    }
};
