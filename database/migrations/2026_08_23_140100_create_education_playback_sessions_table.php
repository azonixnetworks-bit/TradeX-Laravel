<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'education_playback_sessions',
            function (Blueprint $table): void {
                $table->id();

                $table->uuid(
                    'uuid'
                )->unique();

                $table->char(
                    'token_hash',
                    64
                )->unique();

                $table->unsignedBigInteger(
                    'user_id'
                );

                $table->unsignedBigInteger(
                    'user_device_id'
                );

                $table->unsignedBigInteger(
                    'lesson_video_id'
                );

                $table->uuid(
                    'sanctum_session_uuid'
                );

                $table->string(
                    'request_ip',
                    45
                )->nullable();

                $table->char(
                    'user_agent_hash',
                    64
                )->nullable();

                $table->timestamp(
                    'expires_at'
                );

                $table->timestamp(
                    'last_used_at'
                )->nullable();

                $table->unsignedInteger(
                    'use_count'
                )->default(0);

                $table->timestamp(
                    'revoked_at'
                )->nullable();

                $table->timestamps();

                $table->foreign(
                    'user_id',
                    'edu_playback_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();

                $table->foreign(
                    'user_device_id',
                    'edu_playback_device_fk'
                )
                    ->references('id')
                    ->on('user_devices')
                    ->cascadeOnDelete();

                $table->foreign(
                    'lesson_video_id',
                    'edu_playback_video_fk'
                )
                    ->references('id')
                    ->on('lesson_videos')
                    ->cascadeOnDelete();

                $table->index(
                    [
                        'user_id',
                        'expires_at',
                    ],
                    'edu_playback_user_expiry_idx'
                );

                $table->index(
                    [
                        'lesson_video_id',
                        'expires_at',
                    ],
                    'edu_playback_video_expiry_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'education_playback_sessions'
        );
    }
};
