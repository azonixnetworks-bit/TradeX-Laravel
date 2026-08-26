<?php

namespace App\Services\Education;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\LessonVideo;
use App\Models\User;

class EducationCatalogService
{
    public function __construct(
        private readonly EducationPresenter $presenter,
        private readonly EducationAccessService $access
    ) {
    }

    public function courses(
        User $user,
        array $filters
    ): array {
        $query = Course::query()
            ->published()
            ->ordered();

        if (filled($filters['language_code'] ?? null)) {
            $query->where(
                'language_code',
                $filters['language_code']
            );
        }

        if (filled($filters['access_level'] ?? null)) {
            $query->where(
                'access_level',
                $filters['access_level']
            );
        }

        if (array_key_exists('featured', $filters)) {
            $query->where(
                'is_featured',
                (bool) $filters['featured']
            );
        }

        $perPage = (int) (
            $filters['per_page']
            ?? config('tradex_education.courses_per_page', 20)
        );

        $paginator = $query->paginate($perPage);

        return [
            'items' => collect($paginator->items())
                ->map(
                    fn (Course $course): array =>
                        $this->presenter
                            ->courseSummary($user, $course)
                )
                ->values()
                ->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function course(
        User $user,
        Course $course
    ): array {
        $modules = CourseModule::query()
            ->published()
            ->forCourse($course)
            ->ordered()
            ->get();

        foreach ($modules as $module) {
            $module->setRelation(
                'lessons',
                Lesson::query()
                    ->published()
                    ->forModule($module)
                    ->ordered()
                    ->get()
            );
        }

        return [
            'course' => $this->presenter
                ->courseDetail($user, $course),
            'modules' => $modules
                ->map(
                    fn (CourseModule $module): array =>
                        array_merge(
                            $this->presenter
                                ->moduleSummary($user, $module),
                            [
                                'lessons' => $module->lessons
                                    ->map(
                                        fn (Lesson $lesson): array =>
                                            $this->presenter
                                                ->lessonSummary(
                                                    $user,
                                                    $lesson
                                                )
                                    )
                                    ->values()
                                    ->all(),
                            ]
                        )
                )
                ->values()
                ->all(),
        ];
    }

    public function module(
        User $user,
        CourseModule $module
    ): array {
        $lessons = Lesson::query()
            ->published()
            ->forModule($module)
            ->ordered()
            ->get();

        $module->setRelation('lessons', $lessons);

        return [
            'course' => $this->presenter
                ->courseSummary($user, $module->course),
            'module' => $this->presenter
                ->moduleDetail($user, $module),
            'lessons' => $lessons
                ->map(
                    fn (Lesson $lesson): array =>
                        $this->presenter
                            ->lessonSummary($user, $lesson)
                )
                ->values()
                ->all(),
        ];
    }

    public function lesson(
        User $user,
        Lesson $lesson
    ): array {
        $this->access
            ->assertLessonAccess($user, $lesson);

        $videos = LessonVideo::query()
            ->forLesson($lesson)
            ->playbackReady()
            ->ordered()
            ->get();

        $assignmentCount = Assignment::query()
            ->forLesson($lesson)
            ->published()
            ->count();

        return [
            'course' => $this->presenter
                ->courseSummary(
                    $user,
                    $lesson->module->course
                ),
            'module' => $this->presenter
                ->moduleSummary($user, $lesson->module),
            'lesson' => array_merge(
                $this->presenter
                    ->lessonDetail($user, $lesson),
                [
                    'published_assignment_count' =>
                        $assignmentCount,
                    'has_assignments' =>
                        $assignmentCount > 0,
                ]
            ),
            'videos' => $videos
                ->map(
                    fn (LessonVideo $video): array =>
                        $this->presenter
                            ->videoSummary($user, $video)
                )
                ->values()
                ->all(),
        ];
    }
}
