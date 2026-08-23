<?php

namespace App\Services\Education;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\LessonVideo;

class EducationResolver
{
    public function course(
        string $uuid
    ): Course {
        return Course::query()
            ->published()
            ->where(
                'uuid',
                $uuid
            )
            ->firstOrFail();
    }

    public function module(
        string $uuid
    ): CourseModule {
        return CourseModule::query()
            ->published()
            ->where(
                'uuid',
                $uuid
            )
            ->whereHas(
                'course',
                fn ($query) =>
                    $query->published()
            )
            ->firstOrFail();
    }

    public function lesson(
        string $uuid
    ): Lesson {
        return Lesson::query()
            ->published()
            ->where(
                'uuid',
                $uuid
            )
            ->whereHas(
                'module',
                fn ($query) =>
                    $query
                        ->published()
                        ->whereHas(
                            'course',
                            fn ($course) =>
                                $course
                                    ->published()
                        )
            )
            ->firstOrFail();
    }

    public function video(
        string $uuid
    ): LessonVideo {
        return LessonVideo::query()
            ->playbackReady()
            ->whereNotNull(
                'hls_master_path'
            )
            ->where(
                'hls_master_path',
                '!=',
                ''
            )
            ->where(
                'uuid',
                $uuid
            )
            ->whereHas(
                'lesson',
                fn ($query) =>
                    $query
                        ->published()
                        ->whereHas(
                            'module',
                            fn ($module) =>
                                $module
                                    ->published()
                                    ->whereHas(
                                        'course',
                                        fn ($course) =>
                                            $course
                                                ->published()
                                    )
                        )
            )
            ->firstOrFail();
    }
}
