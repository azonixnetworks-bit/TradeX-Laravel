<?php

namespace App\Services\Education;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonVideo;
use App\Models\User;
use App\Models\UserLessonProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EducationProgressService
{
    public function update(
        User $user,
        Lesson $lesson,
        array $data,
        Request $request
    ): UserLessonProgress {
        $video = null;

        if (
            filled(
                $data['last_video_uuid']
                ?? null
            )
        ) {
            $video =
                LessonVideo::query()
                    ->playbackReady()
                    ->where(
                        'uuid',
                        $data['last_video_uuid']
                    )
                    ->where(
                        'lesson_id',
                        $lesson->getKey()
                    )
                    ->first();

            if (! $video) {
                throw new AuthApiException(
                    422,
                    'INVALID_LESSON_VIDEO',
                    'The selected video does not belong to this lesson or is not playable.'
                );
            }
        }

        return DB::transaction(
            function () use (
                $user,
                $lesson,
                $data,
                $video,
                $request
            ): UserLessonProgress {
                $progress =
                    UserLessonProgress::query()
                        ->where(
                            'user_id',
                            $user->getKey()
                        )
                        ->where(
                            'lesson_id',
                            $lesson->getKey()
                        )
                        ->lockForUpdate()
                        ->first();

                if (! $progress) {
                    $progress =
                        new UserLessonProgress([
                            'user_id' =>
                                $user->getKey(),

                            'lesson_id' =>
                                $lesson->getKey(),

                            'started_at' =>
                                now(),
                        ]);
                }

                $requestedStatus =
                    $data['status'];

                if (
                    $progress->status
                    === UserLessonProgress::STATUS_COMPLETED
                ) {
                    $requestedStatus =
                        UserLessonProgress::STATUS_COMPLETED;
                }

                $progress->forceFill([
                    'status' =>
                        $requestedStatus,

                    'last_video_id' =>
                        $video?->getKey()
                        ?? $progress->last_video_id,

                    'last_position_seconds' =>
                        $data['last_position_seconds']
                        ?? $progress->last_position_seconds
                        ?? 0,

                    'started_at' =>
                        $progress->started_at
                        ?? now(),

                    'completed_at' =>
                        $requestedStatus
                        === UserLessonProgress::STATUS_COMPLETED
                            ? (
                                $progress->completed_at
                                ?? now()
                            )
                            : null,

                    'last_activity_at' =>
                        now(),
                ])->save();

                AuditLog::record(
                    'USER',
                    $user->getKey(),
                    $requestedStatus
                    === UserLessonProgress::STATUS_COMPLETED
                        ? 'EDUCATION_LESSON_COMPLETED'
                        : 'EDUCATION_LESSON_PROGRESS_UPDATED',
                    'EDUCATION',
                    'LESSON',
                    $lesson->getKey(),
                    null,
                    [
                        'status' =>
                            $progress->status,

                        'last_position_seconds' =>
                            $progress
                                ->last_position_seconds,
                    ],
                    $request->ip(),
                    $request->userAgent()
                );

                return $progress;
            }
        );
    }

    public function lessonProgress(
        User $user,
        Lesson $lesson
    ): array {
        $progress =
            UserLessonProgress::query()
                ->where(
                    'user_id',
                    $user->getKey()
                )
                ->where(
                    'lesson_id',
                    $lesson->getKey()
                )
                ->first();

        if (! $progress) {
            return [
                'status' =>
                    'NOT_STARTED',

                'last_position_seconds' =>
                    0,

                'completed_at' =>
                    null,
            ];
        }

        return [
            'status' =>
                $progress->status,

            'last_position_seconds' =>
                $progress
                    ->last_position_seconds,

            'completed_at' =>
                $progress
                    ->completed_at
                    ?->toIso8601String(),
        ];
    }

    public function courseProgress(
        User $user,
        Course $course
    ): array {
        $lessonIds =
            Lesson::query()
                ->published()
                ->whereHas(
                    'module',
                    fn ($query) =>
                        $query
                            ->published()
                            ->where(
                                'course_id',
                                $course->getKey()
                            )
                )
                ->pluck('id');

        $total =
            $lessonIds->count();

        if ($total === 0) {
            return [
                'total_lessons' => 0,
                'completed_lessons' => 0,
                'percent' => 0,
            ];
        }

        $completed =
            UserLessonProgress::query()
                ->where(
                    'user_id',
                    $user->getKey()
                )
                ->whereIn(
                    'lesson_id',
                    $lessonIds
                )
                ->where(
                    'status',
                    UserLessonProgress::STATUS_COMPLETED
                )
                ->count();

        return [
            'total_lessons' =>
                $total,

            'completed_lessons' =>
                $completed,

            'percent' =>
                (int) round(
                    ($completed / $total)
                    * 100
                ),
        ];
    }
}
