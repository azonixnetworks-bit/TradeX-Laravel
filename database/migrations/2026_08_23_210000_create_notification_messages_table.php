<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'notification_messages',
            function (
                Blueprint $table
            ): void {
                $table->id();

                $table->uuid('uuid')
                    ->unique();

                $table->string(
                    'audience_access_level',
                    20
                )->default('ALL');

                $table->string(
                    'category',
                    30
                )->default('GENERAL');

                $table->string(
                    'priority',
                    20
                )->default('NORMAL');

                $table->string(
                    'title',
                    180
                );

                $table->text(
                    'body'
                );

                $table->string(
                    'action_type',
                    40
                )->default('NONE');

                $table->uuid(
                    'action_resource_uuid'
                )->nullable();

                $table->boolean(
                    'is_active'
                )->default(true);

                $table->timestamp(
                    'published_at'
                )->nullable();

                $table->timestamp(
                    'expires_at'
                )->nullable();

                $table->foreignId(
                    'created_by_admin_id'
                )->nullable();

                $table->timestamps();

                $table->foreign(
                    'created_by_admin_id',
                    'notif_msg_admin_fk'
                )
                    ->references('id')
                    ->on('admin_users')
                    ->nullOnDelete();

                $table->index(
                    [
                        'is_active',
                        'published_at',
                    ],
                    'notif_msg_publish_idx'
                );

                $table->index(
                    [
                        'audience_access_level',
                        'category',
                    ],
                    'notif_msg_audience_cat_idx'
                );

                $table->index(
                    'expires_at',
                    'notif_msg_expires_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'notification_messages'
        );
    }
};
