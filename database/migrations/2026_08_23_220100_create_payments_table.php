<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'payments',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->foreignId(
                    'payment_order_id'
                )->unique();

                $table->foreignId('user_id');

                $table->string('provider', 30);
                $table->string('currency', 8);

                $table->unsignedBigInteger(
                    'gross_amount_minor'
                );

                $table->unsignedBigInteger(
                    'processor_fee_minor'
                )->nullable();

                $table->unsignedBigInteger(
                    'net_eligible_revenue_minor'
                )->nullable();

                $table->char(
                    'provider_capture_reference_hash',
                    64
                )->unique();

                $table->text(
                    'provider_capture_reference'
                );

                $table->string(
                    'status',
                    30
                )->default('CLEARED');

                $table->string(
                    'affiliate_processing_status',
                    30
                )->default('NOT_APPLICABLE');

                $table->timestamp(
                    'affiliate_processed_at'
                )->nullable();

                $table->timestamp(
                    'cleared_at'
                );

                $table->timestamp(
                    'payment_reversed_at'
                )->nullable();

                $table->string(
                    'payment_reversal_code',
                    80
                )->nullable();

                $table->text(
                    'payment_reversal_reason'
                )->nullable();

                $table->timestamps();

                $table->foreign(
                    'payment_order_id',
                    'payments_order_fk'
                )
                    ->references('id')
                    ->on('payment_orders')
                    ->restrictOnDelete();

                $table->foreign(
                    'user_id',
                    'payments_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->restrictOnDelete();

                $table->index(
                    [
                        'user_id',
                        'status',
                        'cleared_at',
                    ],
                    'payments_user_status_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'payments'
        );
    }
};
