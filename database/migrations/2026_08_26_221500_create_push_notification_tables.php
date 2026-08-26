<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'push_device_tokens',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id');
                $table->foreignId('user_device_id');
                $table->string('provider', 10);
                $table->string('platform', 20);
                $table->char('token_hash', 64)->unique();
                $table->text('token_ciphertext');
                $table->boolean('is_active')->default(true);
                $table->timestamp('registered_at');
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('invalidated_at')->nullable();
                $table->timestamps();

                $table->foreign('user_id', 'push_token_user_fk')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();

                $table->foreign('user_device_id', 'push_token_device_fk')
                    ->references('id')
                    ->on('user_devices')
                    ->cascadeOnDelete();

                $table->index(
                    ['user_id', 'user_device_id', 'is_active'],
                    'push_token_user_device_idx'
                );

                $table->index(
                    ['provider', 'is_active'],
                    'push_token_provider_idx'
                );
            }
        );

        Schema::create(
            'push_notification_deliveries',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_notification_receipt_id');
                $table->foreignId('push_device_token_id')->nullable();
                $table->foreignId('user_id');
                $table->string('provider', 10);
                $table->string('status', 20)->default('PENDING');
                $table->unsignedInteger('attempts')->default(0);
                $table->unsignedSmallInteger('last_http_status')->nullable();
                $table->string('provider_message_id', 255)->nullable();
                $table->string('last_error_code', 100)->nullable();
                $table->timestamp('queued_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamp('next_attempt_at')->nullable();
                $table->timestamps();

                $table->unique(
                    [
                        'user_notification_receipt_id',
                        'push_device_token_id',
                    ],
                    'push_delivery_receipt_token_uq'
                );

                $table->foreign(
                    'user_notification_receipt_id',
                    'push_delivery_receipt_fk'
                )
                    ->references('id')
                    ->on('user_notification_receipts')
                    ->cascadeOnDelete();

                $table->foreign(
                    'push_device_token_id',
                    'push_delivery_token_fk'
                )
                    ->references('id')
                    ->on('push_device_tokens')
                    ->nullOnDelete();

                $table->foreign('user_id', 'push_delivery_user_fk')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();

                $table->index(
                    ['status', 'next_attempt_at'],
                    'push_delivery_status_due_idx'
                );

                $table->index(
                    ['user_id', 'created_at'],
                    'push_delivery_user_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('push_notification_deliveries');
        Schema::dropIfExists('push_device_tokens');
    }
};
