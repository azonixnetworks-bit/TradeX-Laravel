<?php

namespace App\Services\Education;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use App\Models\UserLessonBookmark;
use App\Models\UserLessonProgress;
use App\Services\Dashboard\DashboardApiService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class LearningHubService
{
    public function __construct(
        private readonly DashboardApiService $dashboard,
        private readonly EducationCatalogService $catalog
    ) {}

    public function overview(User $user): array
    {
        $featured = $this->catalog->courses($user, [
            'featured' => true,
            'per_page' => 6,
        ]);

        return [
            'is_premium' => $user->canAccessPremium(),
            'learning' => $this->dashboard->learningSummary($user),
            'featured_courses' => $featured['items'],
            'recent_learning' => $this->progressItems($user, 'RECENT', 3)['items'],
            'assignment_summary' => [
                'available' => $this->assignmentQuery($user)->count(),
                'submission_method' => 'WHATSAPP',
                'submission_status_persisted' => false,
            ],
        ];
    }

    public function myLearning(User $user, string $view, int $perPage): array
    {
        return $this->progressItems($user, $view, $perPage);
    }

    public function progress(User $user): array
    {
        $accessible = $this->accessibleLessons($user)->select('lessons.id');
        $total = (clone $accessible)->count();
        $counts = UserLessonProgress::query()
            ->select('status')
            ->selectRaw('COUNT(*) AS aggregate')
            ->where('user_id', $user->getKey())
            ->whereIn('lesson_id', clone $accessible)
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $completed = (int) $counts->get(UserLessonProgress::STATUS_COMPLETED, 0);
        $inProgress = (int) $counts->get(UserLessonProgress::STATUS_IN_PROGRESS, 0);

        return [
            'overall' => [
                'total_lessons' => $total,
                'completed_lessons' => $completed,
                'in_progress_lessons' => $inProgress,
                'percent' => $total === 0 ? 0 : (int) round(($completed / $total) * 100),
            ],
            'courses' => $this->catalog->courses($user, ['per_page' => 50])['items'],
            'recent_completions' => $this->progressItems($user, 'COMPLETED', 5)['items'],
        ];
    }

    public function assignments(User $user, string $status, int $perPage): array
    {
        if (in_array($status, ['SUBMITTED', 'REVIEWED'], true)) {
            return [
                'items' => [],
                'pagination' => $this->emptyPagination($perPage),
                'submission_policy' => $this->assignmentPolicy(),
            ];
        }

        $paginator = $this->assignmentQuery($user)->paginate($perPage);

        return [
            'items' => collect($paginator->items())
                ->map(fn (Assignment $assignment): array => $this->assignmentItem($assignment))
                ->values()
                ->all(),
            'pagination' => $this->pagination($paginator),
            'submission_policy' => $this->assignmentPolicy(),
        ];
    }

    private function progressItems(User $user, string $view, int $perPage): array
    {
        if ($view === 'SAVED') {
            $paginator = UserLessonBookmark::query()
                ->with('lesson.module.course')
                ->where('user_id', $user->getKey())
                ->latest('created_at')
                ->paginate($perPage);
            $lessons = collect($paginator->items())
                ->map(fn (UserLessonBookmark $bookmark) => $bookmark->lesson)
                ->filter(fn ($lesson) => $lesson instanceof Lesson);
            $progress = $this->progressByLesson($user, $lessons->pluck('id')->all());
            $items = $lessons
                ->map(fn (Lesson $lesson): array => $this->learningItem(
                    $lesson,
                    $progress->get($lesson->getKey()),
                    true
                ))
                ->values()
                ->all();
        } else {
            $query = UserLessonProgress::query()
                ->with(['lesson.module.course', 'lastVideo'])
                ->where('user_id', $user->getKey())
                ->whereHas('lesson', fn (Builder $lesson): Builder => $this->accessibleLessonRelation($lesson, $user));

            if ($view === 'CONTINUE') {
                $query->where('status', UserLessonProgress::STATUS_IN_PROGRESS);
            } elseif ($view === 'COMPLETED') {
                $query->where('status', UserLessonProgress::STATUS_COMPLETED);
            }

            $query->orderByDesc(
                $view === 'COMPLETED' ? 'completed_at' : 'last_activity_at'
            );
            $paginator = $query->paginate($perPage);
            $lessonIds = collect($paginator->items())->pluck('lesson_id')->all();
            $bookmarked = UserLessonBookmark::query()
                ->where('user_id', $user->getKey())
                ->whereIn('lesson_id', $lessonIds)
                ->pluck('lesson_id')
                ->flip();
            $items = collect($paginator->items())
                ->filter(fn ($progress) => $progress instanceof UserLessonProgress && $progress->lesson instanceof Lesson)
                ->map(fn (UserLessonProgress $progress): array => $this->learningItem(
                    $progress->lesson,
                    $progress,
                    $bookmarked->has($progress->lesson_id)
                ))
                ->values()
                ->all();
        }

        return [
            'items' => $items,
            'pagination' => $this->pagination($paginator),
        ];
    }

    private function learningItem(
        Lesson $lesson,
        ?UserLessonProgress $progress,
        bool $bookmarked
    ): array {
        $module = $lesson->module;
        $course = $module?->course;

        return [
            'course' => [
                'uuid' => $course?->uuid,
                'title' => $course?->title,
                'access_level' => $course?->access_level,
            ],
            'module' => [
                'uuid' => $module?->uuid,
                'title' => $module?->title,
            ],
            'lesson' => [
                'uuid' => $lesson->uuid,
                'title' => $lesson->title,
                'is_preview' => (bool) $lesson->is_preview,
            ],
            'status' => $progress?->status ?? 'NOT_STARTED',
            'last_video_uuid' => $progress?->lastVideo?->uuid,
            'last_position_seconds' => (int) ($progress?->last_position_seconds ?? 0),
            'last_activity_at' => $progress?->last_activity_at?->toISOString(),
            'completed_at' => $progress?->completed_at?->toISOString(),
            'bookmarked' => $bookmarked,
        ];
    }

    private function assignmentQuery(User $user): Builder
    {
        $query = Assignment::query()
            ->published()
            ->with('lesson.module.course')
            ->whereHas('lesson', function (Builder $lesson) use ($user): void {
                $lesson->published()->whereHas('module', function (Builder $module) use ($user): void {
                    $module->published()->whereHas('course', function (Builder $course) use ($user): void {
                        $course->published();
                        if (! $user->canAccessPremium()) {
                            $course->where('access_level', Course::ACCESS_FREE);
                        }
                    });
                });
            })
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        return $query;
    }

    private function assignmentItem(Assignment $assignment): array
    {
        $lesson = $assignment->lesson;
        $module = $lesson?->module;
        $course = $module?->course;

        return [
            'uuid' => $assignment->uuid,
            'title' => $assignment->title,
            'is_required' => (bool) $assignment->is_required,
            'submission_method' => $assignment->submission_method,
            'state' => 'PENDING',
            'lesson' => ['uuid' => $lesson?->uuid, 'title' => $lesson?->title],
            'module' => ['uuid' => $module?->uuid, 'title' => $module?->title],
            'course' => ['uuid' => $course?->uuid, 'title' => $course?->title],
            'material_available' => $assignment->hasMaterial(),
        ];
    }

    private function accessibleLessons(User $user): Builder
    {
        return $this->accessibleLessonRelation(Lesson::query(), $user);
    }

    private function accessibleLessonRelation(Builder $lesson, User $user): Builder
    {
        $lesson->published()->whereHas('module', function (Builder $module) use ($user): void {
            $module->published()->whereHas('course', function (Builder $course) use ($user): void {
                $course->published();
                if (! $user->canAccessPremium()) {
                    $course->where(function (Builder $access): void {
                        $access->where('access_level', Course::ACCESS_FREE);
                    });
                }
            });
        });

        if (! $user->canAccessPremium()) {
            $lesson->where(function (Builder $access): void {
                $access->where('is_preview', true)
                    ->orWhereHas('module.course', fn (Builder $course): Builder => $course->where('access_level', Course::ACCESS_FREE));
            });
        }

        return $lesson;
    }

    private function progressByLesson(User $user, array $lessonIds)
    {
        return UserLessonProgress::query()
            ->with('lastVideo')
            ->where('user_id', $user->getKey())
            ->whereIn('lesson_id', $lessonIds)
            ->get()
            ->keyBy('lesson_id');
    }

    private function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    private function emptyPagination(int $perPage): array
    {
        return ['current_page' => 1, 'last_page' => 1, 'per_page' => $perPage, 'total' => 0];
    }

    private function assignmentPolicy(): array
    {
        return [
            'student_upload_to_tradex' => false,
            'external_submission_supported' => true,
            'supported_external_method' => 'WHATSAPP',
            'submission_status_persisted' => false,
        ];
    }
}
