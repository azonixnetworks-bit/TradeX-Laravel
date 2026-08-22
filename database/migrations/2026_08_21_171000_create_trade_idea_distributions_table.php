<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'trade_idea_distributions',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->foreignId('trade_idea_id')
                    ->constrained('trade_ideas')
                    ->cascadeOnDelete();

                $table->foreignId('trade_idea_update_id')
                    ->nullable()
                    ->constrained('trade_idea_updates')
                    ->nullOnDelete();

                $table->string(
                    'event_type',
                    24
                );

                $table->string(
                    'channel',
                    20
                );

                /*
                | Raw destination is encrypted through the model cast.
                | destination_hash supports dedupe/search without revealing it.
                */
                $table->text(
                    'destination'
                );

                $table->char(
                    'destination_hash',
                    64
                );

                $table->char(
                    'idempotency_key',
                    64
                )->unique();

                $table->string(
                    'status',
                    20
                )->default('PENDING');

                $table->char(
                    'payload_sha256',
                    64
                );

                $table->json(
                    'payload_snapshot'
                );

                $table->unsignedSmallInteger(
                    'attempt_count'
                )->default(0);

                $table->string(
                    'provider_message_id',
                    255
                )->nullable();

                $table->string(
                    'last_error_code',
                    100
                )->nullable();

                $table->text(
                    'last_error_message'
                )->nullable();

                $table->json(
                    'response_meta'
                )->nullable();

                $table->timestamp(
                    'queued_at'
                )->nullable();

                $table->timestamp(
                    'started_at'
                )->nullable();

                $table->timestamp(
                    'sent_at'
                )->nullable();

                $table->timestamp(
                    'failed_at'
                )->nullable();

                $table->foreignId(
                    'created_by_admin_id'
                )
                    ->nullable()
                    ->constrained('admin_users')
                    ->nullOnDelete();

                $table->timestamps();

                $table->index([
                    'trade_idea_id',
                    'status',
                ]);

                $table->index([
                    'channel',
                    'status',
                ]);

                $table->index([
                    'destination_hash',
                    'channel',
                ]);

                $table->index([
                    'trade_idea_update_id',
                    'channel',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'trade_idea_distributions'
        );
    }
};
