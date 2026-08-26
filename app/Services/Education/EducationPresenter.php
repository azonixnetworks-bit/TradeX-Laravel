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
        private readonly EducationAccessService $access,
        private readonly EducationProgressService $progress,
        private readonly EducationContentLocalizationService $localization
    ) {
    }

    public function courseSummary(
        User $user,
        Course $course
    ): array {
        return $this->coursePayload(
            $user,
            $course,
            false
        );
    }

    public function courseDetail(
        User $user,
        Course $course
    ): array {
        return $this->coursePayload(
            $user,
            $course,
            true
        );
    }

    public function moduleSummary(
        User $user,
        CourseModule $module
    ): array {
        return $this->modulePayload(
            $user,
            $module,
            false
        );
    }

    public function moduleDetail(
        User $user,
        CourseModule $module
    ): array {
        return $this->modulePayload(
            $user,
            $module,
            true
        );
    }

    public function lessonSummary(
        User $user,
        Lesson $lesson
    ): array {
        return $this->lessonPayload(
            $user,
            $lesson,
            false
        );
    }

    public function lessonDetail(
        User $user,
        Lesson $lesson
    ): array {
        return $this->lessonPayload(
            $user,
            $lesson,
            true
        );
    }

    public function videoSummary(
        User $user,
        LessonVideo $video
    ): array {
        $localized = $this->localization
            ->video($user, $video);
        $fields = $localized['fields'];

        return [
            'uuid' => $video->uuid,
            'title' => $fields['title'],
            'short_description' =>
                $fields['short_description'],
            'duration_seconds' =>
                $video->duration_seconds,
            'playback_ready' =>
                $video->isPlaybackReady()
                && $video->hasHlsStream(),
            'is_downloadable' => false,
            'sort_order' => $video->sort_order,
            'localization' =>
                $localized['localization'],
        ];
    }

    private function coursePayload(
        User $user,
        Course $course,
        bool $includeDescription
    ): array {
        $canAccess = $this->access
            ->canAccessCourse($user, $course);
        $localized = $this->localization
            ->course($user, $course);
        $fields = $localized['fields'];

        $payload = [
            'uuid' => $course->uuid,
            'code' => $course->code,
            'slug' => $course->slug,
            'title' => $fields['title'],
            'short_description' =>
                $fields['short_description'],
            /*
            | language_code remains the source-language catalog field.
            | Display language is reported under localization.*.
            */
            'language_code' => $course->language_code,
            'access_level' => $course->access_level,
            'is_featured' => (bool) $course->is_featured,
            'can_access' => $canAccess,
            'is_locked' => ! $canAccess,
            'progress' => $this->progress
                ->courseProgress($user, $course),
            'localization' =>
                $localized['localization'],
        ];

        if ($includeDescription) {
            $payload['description'] =
                $fields['description'];
        }

        return $payload;
    }

    private function modulePayload(
        User $user,
        CourseModule $module,
        bool $includeDescription
    ): array {
        $localized = $this->localization
            ->module($user, $module);
        $fields = $localized['fields'];

        $payload = [
            'uuid' => $module->uuid,
            'code' => $module->code,
            'slug' => $module->slug,
            'title' => $fields['title'],
            'short_description' =>
                $fields['short_description'],
            'sort_order' => $module->sort_order,
            'lesson_count' =>
                $module->relationLoaded('lessons')
                    ? $module->lessons->count()
                    : Lesson::query()
                        ->published()
                        ->forModule($module)
                        ->count(),
            'localization' =>
                $localized['localization'],
        ];

        if ($includeDescription) {
            $payload['description'] =
                $fields['description'];
        }

        return $payload;
    }

    private function lessonPayload(
        User $user,
        Lesson $lesson,
        bool $includeDescription
    ): array {
        $canAccess = $this->access
            ->canAccessLesson($user, $lesson);
        $localized = $this->localization
            ->lesson($user, $lesson);
        $fields = $localized['fields'];

        $payload = [
            'uuid' => $lesson->uuid,
            'code' => $lesson->code,
            'slug' => $lesson->slug,
            'title' => $fields['title'],
            'heading' => $fields['heading'],
            'short_description' =>
                $fields['short_description'],
            'is_preview' => (bool) $lesson->is_preview,
            'can_access' => $canAccess,
            'is_locked' => ! $canAccess,
            'sort_order' => $lesson->sort_order,
            'progress' => $this->progress
                ->lessonProgress($user, $lesson),
            'localization' =>
                $localized['localization'],
        ];

        if ($includeDescription) {
            $payload['description'] =
                $fields['description'];
        }

        return $payload;
    }
}
