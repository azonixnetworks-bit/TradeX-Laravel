<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'user_notification_receipts',
            function (
                Blueprint $table
            ): void {
                $table->id();

                $table->uuid('uuid')
                    ->unique();

                $table->foreignId(
                    'notification_message_id'
                );

                $table->foreignId(
                    'user_id'
                );

                $table->timestamp(
                    'read_at'
                )->nullable();

                $table->timestamps();

                $table->foreign(
                    'notification_message_id',
                    'usr_notif_msg_fk'
                )
                    ->references('id')
                    ->on('notification_messages')
                    ->restrictOnDelete();

                $table->foreign(
                    'user_id',
                    'usr_notif_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->restrictOnDelete();

                $table->unique(
                    [
                        'notification_message_id',
                        'user_id',
                    ],
                    'usr_notif_msg_user_uq'
                );

                $table->index(
                    [
                        'user_id',
                        'read_at',
                    ],
                    'usr_notif_user_read_idx'
                );

                $table->index(
                    [
                        'user_id',
                        'created_at',
                    ],
                    'usr_notif_user_created_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'user_notification_receipts'
        );
    }
};
