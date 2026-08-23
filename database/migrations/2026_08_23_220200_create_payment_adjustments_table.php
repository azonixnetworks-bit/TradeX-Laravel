<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'payment_adjustments',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->foreignId('payment_id');

                $table->string('type', 40);
                $table->bigInteger('amount_minor');
                $table->string('currency', 8);

                $table->text('reason');

                $table->string(
                    'provider_event_id',
                    191
                )->nullable();

                $table->timestamps();

                $table->foreign(
                    'payment_id',
                    'pay_adj_payment_fk'
                )
                    ->references('id')
                    ->on('payments')
                    ->restrictOnDelete();

                $table->unique(
                    [
                        'payment_id',
                        'type',
                    ],
                    'pay_adj_payment_type_uq'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'payment_adjustments'
        );
    }
};
