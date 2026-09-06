<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'notification_automation_events',
            function (Blueprint $table): void {
                $table->id();
                $table->string('event_key', 64)->unique();
                $table->string('event_type', 80);
                $table->string('source_type', 80)->nullable();
                $table->string('source_reference', 191)->nullable();
                $table->json('payload');
                $table->string('status', 20)->default('PENDING');
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->timestamp('next_attempt_at')->nullable();
                $table->string('last_error_code', 80)->nullable();
                $table->foreignId('notification_message_id')->nullable();
                $table->unsignedInteger('recipient_count')->default(0);
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->foreign(
                    'notification_message_id',
                    'notif_auto_event_message_fk'
                )
                    ->references('id')
                    ->on('notification_messages')
                    ->nullOnDelete();

                $table->index(
                    ['status', 'next_attempt_at', 'id'],
                    'notif_auto_event_due_idx'
                );

                $table->index(
                    ['event_type', 'created_at'],
                    'notif_auto_event_type_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_automation_events');
    }
};
