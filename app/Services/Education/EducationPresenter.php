<?php

namespace App\Services\Education;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\LessonVideo;
use App\Models\User;

class EducationPresenter
{
    public function __construct(
        private readonly EducationAccessService
            $access,
        private readonly EducationProgressService
            $progress
    ) {
    }

    public function courseSummary(
        User $user,
        Course $course
    ): array {
        $canAccess =
            $this->access
                ->canAccessCourse(
                    $user,
                    $course
                );

        return [
            'uuid' =>
                $course->uuid,

            'code' =>
                $course->code,

            'slug' =>
                $course->slug,

            'title' =>
                $course->title,

            'short_description' =>
                $course->short_description,

            'language_code' =>
                $course->language_code,

            'access_level' =>
                $course->access_level,

            'is_featured' =>
                (bool)
                    $course->is_featured,

            'can_access' =>
                $canAccess,

            'is_locked' =>
                ! $canAccess,

            'progress' =>
                $this->progress
                    ->courseProgress(
                        $user,
                        $course
                    ),
        ];
    }

    public function moduleSummary(
        User $user,
        CourseModule $module
    ): array {
        return [
            'uuid' =>
                $module->uuid,

            'code' =>
                $module->code,

            'slug' =>
                $module->slug,

            'title' =>
                $module->title,

            'short_description' =>
                $module->short_description,

            'sort_order' =>
                $module->sort_order,

            'lesson_count' =>
                $module->relationLoaded(
                    'lessons'
                )
                    ? $module->lessons
                        ->count()
                    : Lesson::query()
                        ->published()
                        ->forModule(
                            $module
                        )
                        ->count(),
        ];
    }

    public function lessonSummary(
        User $user,
        Lesson $lesson
    ): array {
        $canAccess =
            $this->access
                ->canAccessLesson(
                    $user,
                    $lesson
                );

        return [
            'uuid' =>
                $lesson->uuid,

            'code' =>
                $lesson->code,

            'slug' =>
                $lesson->slug,

            'title' =>
                $lesson->title,

            'heading' =>
                $lesson->heading,

            'short_description' =>
                $lesson
                    ->short_description,

            'is_preview' =>
                (bool)
                    $lesson->is_preview,

            'can_access' =>
                $canAccess,

            'is_locked' =>
                ! $canAccess,

            'sort_order' =>
                $lesson->sort_order,

            'progress' =>
                $this->progress
                    ->lessonProgress(
                        $user,
                        $lesson
                    ),
        ];
    }

    public function videoSummary(
        LessonVideo $video
    ): array {
        return [
            'uuid' =>
                $video->uuid,

            'title' =>
                $video->title,

            'short_description' =>
                $video
                    ->short_description,

            'duration_seconds' =>
                $video
                    ->duration_seconds,

            'playback_ready' =>
                $video
                    ->isPlaybackReady()
                && $video
                    ->hasHlsStream(),

            'is_downloadable' =>
                false,

            'sort_order' =>
                $video
                    ->sort_order,
        ];
    }
}
