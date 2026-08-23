<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'payment_webhook_events',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->string(
                    'provider',
                    30
                );

                $table->string(
                    'event_id',
                    191
                );

                $table->string(
                    'event_type',
                    100
                );

                $table->char(
                    'payload_sha256',
                    64
                );

                $table->string(
                    'status',
                    30
                )->default('RECEIVED');

                $table->timestamp(
                    'processed_at'
                )->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'provider',
                        'event_id',
                    ],
                    'pay_webhook_provider_event_uq'
                );

                $table->index(
                    [
                        'provider',
                        'status',
                        'created_at',
                    ],
                    'pay_webhook_status_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'payment_webhook_events'
        );
    }
};
