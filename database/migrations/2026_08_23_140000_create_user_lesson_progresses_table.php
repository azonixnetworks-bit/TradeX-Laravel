<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'user_lesson_progresses',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'user_id'
                );

                $table->unsignedBigInteger(
                    'lesson_id'
                );

                $table->string(
                    'status',
                    20
                )->default('IN_PROGRESS');

                $table->unsignedBigInteger(
                    'last_video_id'
                )->nullable();

                $table->unsignedInteger(
                    'last_position_seconds'
                )->default(0);

                $table->timestamp(
                    'started_at'
                )->nullable();

                $table->timestamp(
                    'completed_at'
                )->nullable();

                $table->timestamp(
                    'last_activity_at'
                )->nullable();

                $table->timestamps();

                $table->foreign(
                    'user_id',
                    'edu_progress_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();

                $table->foreign(
                    'lesson_id',
                    'edu_progress_lesson_fk'
                )
                    ->references('id')
                    ->on('lessons')
                    ->cascadeOnDelete();

                $table->foreign(
                    'last_video_id',
                    'edu_progress_video_fk'
                )
                    ->references('id')
                    ->on('lesson_videos')
                    ->nullOnDelete();

                $table->unique(
                    [
                        'user_id',
                        'lesson_id',
                    ],
                    'edu_progress_user_lesson_uq'
                );

                $table->index(
                    [
                        'user_id',
                        'status',
                    ],
                    'edu_progress_user_status_idx'
                );

                $table->index(
                    'last_activity_at',
                    'edu_progress_activity_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'user_lesson_progresses'
        );
    }
};
