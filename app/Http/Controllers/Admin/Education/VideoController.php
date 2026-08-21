<?php

namespace App\Http\Controllers\Admin\Education;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Education\StoreVideoRequest;
use App\Http\Requests\Admin\Education\UpdateVideoPublicationRequest;
use App\Http\Requests\Admin\Education\UpdateVideoRequest;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\LessonVideo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VideoController extends Controller
{
    public function index(
        Course $course,
        CourseModule $module,
        Lesson $lesson
    ): View {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'education.view');
        $this->requireLessonOwnership($course, $module, $lesson);

        $videos = $lesson
            ->videos()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $stats = [
            'total' => $videos->count(),
            'draft' => $videos->where('status', LessonVideo::STATUS_DRAFT)->count(),
            'published' => $videos->where('status', LessonVideo::STATUS_PUBLISHED)->count(),
            'archived' => $videos->where('status', LessonVideo::STATUS_ARCHIVED)->count(),
            'pending' => $videos->where('processing_status', LessonVideo::PROCESSING_PENDING)->count(),
            'processing' => $videos->where('processing_status', LessonVideo::PROCESSING_PROCESSING)->count(),
            'ready' => $videos->where('processing_status', LessonVideo::PROCESSING_READY)->count(),
            'failed' => $videos->where('processing_status', LessonVideo::PROCESSING_FAILED)->count(),
            'downloadable' => $videos->where('is_downloadable', true)->count(),
        ];

        $permissions = $this->educationPermissions($admin);

        return view(
            'admin.education.videos.index',
            compact('course', 'module', 'lesson', 'videos', 'stats', 'permissions')
        );
    }

    public function create(
        Course $course,
        CourseModule $module,
        Lesson $lesson
    ): View {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'education.create');
        $this->requireLessonOwnership($course, $module, $lesson);

        return view(
            'admin.education.videos.create',
            compact('course', 'module', 'lesson')
        );
    }

    public function store(
        StoreVideoRequest $request,
        Course $course,
        CourseModule $module,
        Lesson $lesson
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'education.create');
        $this->requireLessonOwnership($course, $module, $lesson);

        $validated = $request->validated();

        DB::transaction(function () use (
            $course,
            $module,
            $lesson,
            $validated,
            $request,
            $admin
        ): void {
            $lockedLesson = Lesson::query()
                ->where('course_module_id', $module->id)
                ->whereHas('module', fn ($query) => $query->where('course_id', $course->id))
                ->lockForUpdate()
                ->findOrFail($lesson->id);

            $video = LessonVideo::query()->create([
                'lesson_id' => $lockedLesson->id,
                'title' => $validated['title'],
                'short_description' => $validated['short_description'] ?? null,
                'description' => $validated['description'] ?? null,
                'storage_disk' => $validated['storage_disk'] ?? 'private',
                'source_path' => $validated['source_path'],
                'hls_master_path' => $validated['hls_master_path'] ?? null,
                'streaming_asset_key' => $validated['streaming_asset_key'] ?? null,
                'thumbnail_path' => $validated['thumbnail_path'] ?? null,
                'duration_seconds' => $validated['duration_seconds'] ?? null,
                'file_size_bytes' => $validated['file_size_bytes'] ?? null,
                'mime_type' => $validated['mime_type'] ?? null,
                'processing_status' => LessonVideo::PROCESSING_PENDING,
                'processing_error' => null,
                'processed_at' => null,
                'status' => LessonVideo::STATUS_DRAFT,
                'published_at' => null,
                'is_downloadable' => (bool) ($validated['is_downloadable'] ?? false),
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'created_by_admin_id' => $admin->id,
                'updated_by_admin_id' => $admin->id,
            ]);

            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $admin->id,
                action: 'VIDEO_CREATED',
                module: 'EDUCATION',
                targetType: 'LESSON_VIDEO',
                targetId: $video->id,
                newValues: $this->auditSnapshot($video),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );
        });

        return redirect()
            ->route(
                'admin.education.courses.modules.lessons.videos.index',
                ['course' => $course, 'module' => $module, 'lesson' => $lesson]
            )
            ->with('success', 'Video created successfully as draft and queued for processing.');
    }

    public function edit(
        Course $course,
        CourseModule $module,
        Lesson $lesson,
        LessonVideo $video
    ): View {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'education.update');
        $this->requireVideoOwnership($course, $module, $lesson, $video);

        return view(
            'admin.education.videos.edit',
            compact('course', 'module', 'lesson', 'video')
        );
    }

    public function update(
        UpdateVideoRequest $request,
        Course $course,
        CourseModule $module,
        Lesson $lesson,
        LessonVideo $video
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'education.update');
        $this->requireVideoOwnership($course, $module, $lesson, $video);

        $validated = $request->validated();

        DB::transaction(function () use (
            $lesson,
            $video,
            $validated,
            $request,
            $admin
        ): void {
            $lockedVideo = LessonVideo::query()
                ->where('lesson_id', $lesson->id)
                ->lockForUpdate()
                ->findOrFail($video->id);

            $before = $this->auditSnapshot($lockedVideo);
            $updates = [];

            foreach ([
                'title',
                'short_description',
                'description',
                'storage_disk',
                'source_path',
                'hls_master_path',
                'streaming_asset_key',
                'thumbnail_path',
                'duration_seconds',
                'file_size_bytes',
                'mime_type',
                'is_downloadable',
                'sort_order',
            ] as $field) {
                if (array_key_exists($field, $validated)) {
                    $updates[$field] = $validated[$field];
                }
            }

            if (array_key_exists('is_downloadable', $updates)) {
                $updates['is_downloadable'] = (bool) ($updates['is_downloadable'] ?? false);
            }

            if (array_key_exists('sort_order', $updates)) {
                $updates['sort_order'] = (int) ($updates['sort_order'] ?? 0);
            }

            $updates['updated_by_admin_id'] = $admin->id;
            $lockedVideo->fill($updates);

            if (! $lockedVideo->isDirty()) {
                return;
            }

            $lockedVideo->save();

            [$oldValues, $newValues] = $this->auditChanges(
                $before,
                $this->auditSnapshot($lockedVideo->fresh())
            );

            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $admin->id,
                action: 'VIDEO_UPDATED',
                module: 'EDUCATION',
                targetType: 'LESSON_VIDEO',
                targetId: $lockedVideo->id,
                oldValues: $oldValues,
                newValues: $newValues,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );
        });

        return redirect()
            ->route(
                'admin.education.courses.modules.lessons.videos.index',
                ['course' => $course, 'module' => $module, 'lesson' => $lesson]
            )
            ->with('success', 'Video updated successfully.');
    }

    public function publication(
        UpdateVideoPublicationRequest $request,
        Course $course,
        CourseModule $module,
        Lesson $lesson,
        LessonVideo $video
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'education.publish');
        $this->requireVideoOwnership($course, $module, $lesson, $video);

        $action = $request->publicationAction();
        $reason = $request->auditReason();

        $message = DB::transaction(function () use (
            $lesson,
            $video,
            $request,
            $admin,
            $action,
            $reason
        ): string {
            $lockedVideo = LessonVideo::query()
                ->where('lesson_id', $lesson->id)
                ->lockForUpdate()
                ->findOrFail($video->id);

            $before = $this->auditSnapshot($lockedVideo);

            if ($action === UpdateVideoPublicationRequest::ACTION_PUBLISH) {
                abort_unless(
                    $lockedVideo->status === LessonVideo::STATUS_DRAFT,
                    422,
                    'Only a draft video can be published.'
                );

                abort_unless(
                    $lockedVideo->processing_status === LessonVideo::PROCESSING_READY,
                    422,
                    'The video must finish processing before publication.'
                );

                abort_unless(
                    $lockedVideo->hasPlaybackReference(),
                    422,
                    'The video requires a protected HLS path or streaming asset reference before publication.'
                );

                $lockedVideo->status = LessonVideo::STATUS_PUBLISHED;
                $lockedVideo->published_at = now();
                $auditAction = 'VIDEO_PUBLISHED';
                $message = 'Video published successfully.';
            } else {
                abort_unless(
                    $lockedVideo->status === LessonVideo::STATUS_PUBLISHED,
                    422,
                    'Only a published video can be unpublished.'
                );

                $lockedVideo->status = LessonVideo::STATUS_DRAFT;
                $lockedVideo->published_at = null;
                $auditAction = 'VIDEO_UNPUBLISHED';
                $message = 'Video unpublished successfully.';
            }

            $lockedVideo->updated_by_admin_id = $admin->id;
            $lockedVideo->save();

            [$oldValues, $newValues] = $this->auditChanges(
                $before,
                $this->auditSnapshot($lockedVideo->fresh())
            );

            if ($reason !== null) {
                $newValues['reason'] = $reason;
            }

            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $admin->id,
                action: $auditAction,
                module: 'EDUCATION',
                targetType: 'LESSON_VIDEO',
                targetId: $lockedVideo->id,
                oldValues: $oldValues,
                newValues: $newValues,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return $message;
        });

        return redirect()
            ->route(
                'admin.education.courses.modules.lessons.videos.index',
                ['course' => $course, 'module' => $module, 'lesson' => $lesson]
            )
            ->with('success', $message);
    }

    public function destroy(
        Request $request,
        Course $course,
        CourseModule $module,
        Lesson $lesson,
        LessonVideo $video
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'education.delete');
        $this->requireVideoOwnership($course, $module, $lesson, $video);

        DB::transaction(function () use (
            $lesson,
            $video,
            $request,
            $admin
        ): void {
            $lockedVideo = LessonVideo::query()
                ->where('lesson_id', $lesson->id)
                ->lockForUpdate()
                ->findOrFail($video->id);

            abort_if(
                $lockedVideo->status === LessonVideo::STATUS_PUBLISHED,
                422,
                'Unpublish the video before deleting it.'
            );

            abort_if(
                $lockedVideo->processing_status === LessonVideo::PROCESSING_PROCESSING,
                422,
                'Wait for active video processing to finish before deleting it.'
            );

            $before = $this->auditSnapshot($lockedVideo);

            $lockedVideo->updated_by_admin_id = $admin->id;
            $lockedVideo->save();
            $lockedVideo->delete();

            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $admin->id,
                action: 'VIDEO_DELETED',
                module: 'EDUCATION',
                targetType: 'LESSON_VIDEO',
                targetId: $lockedVideo->id,
                oldValues: $before,
                newValues: [
                    'deleted_at' => $lockedVideo->deleted_at?->toISOString(),
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );
        });

        return redirect()
            ->route(
                'admin.education.courses.modules.lessons.videos.index',
                ['course' => $course, 'module' => $module, 'lesson' => $lesson]
            )
            ->with('success', 'Video deleted successfully.');
    }

    private function requireVideoOwnership(
        Course $course,
        CourseModule $module,
        Lesson $lesson,
        LessonVideo $video
    ): void {
        $this->requireLessonOwnership($course, $module, $lesson);

        abort_unless(
            (int) $video->lesson_id === (int) $lesson->id,
            404
        );
    }

    private function requireLessonOwnership(
        Course $course,
        CourseModule $module,
        Lesson $lesson
    ): void {
        abort_unless(
            (int) $module->course_id === (int) $course->id,
            404
        );

        abort_unless(
            (int) $lesson->course_module_id === (int) $module->id,
            404
        );
    }

    private function authenticatedAdmin(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }

    private function requirePermission(
        AdminUser $admin,
        string $permission
    ): void {
        abort_unless($admin->hasPermission($permission), 403);
    }

    private function educationPermissions(AdminUser $admin): array
    {
        return [
            'view' => $admin->hasPermission('education.view'),
            'create' => $admin->hasPermission('education.create'),
            'update' => $admin->hasPermission('education.update'),
            'delete' => $admin->hasPermission('education.delete'),
            'publish' => $admin->hasPermission('education.publish'),
        ];
    }

    private function auditSnapshot(LessonVideo $video): array
    {
        return [
            'uuid' => $video->uuid,
            'lesson_id' => $video->lesson_id,
            'title' => $video->title,
            'short_description' => $video->short_description,
            'description_sha256' => $video->description !== null
                ? hash('sha256', $video->description)
                : null,
            'description_length' => $video->description !== null
                ? mb_strlen($video->description)
                : 0,
            'storage_disk' => $video->storage_disk,
            'source_path_sha256' => $video->source_path !== null
                ? hash('sha256', $video->source_path)
                : null,
            'hls_master_path_sha256' => $video->hls_master_path !== null
                ? hash('sha256', $video->hls_master_path)
                : null,
            'streaming_asset_key_sha256' => $video->streaming_asset_key !== null
                ? hash('sha256', $video->streaming_asset_key)
                : null,
            'thumbnail_path_sha256' => $video->thumbnail_path !== null
                ? hash('sha256', $video->thumbnail_path)
                : null,
            'duration_seconds' => $video->duration_seconds,
            'file_size_bytes' => $video->file_size_bytes,
            'mime_type' => $video->mime_type,
            'processing_status' => $video->processing_status,
            'processed_at' => $video->processed_at?->toISOString(),
            'status' => $video->status,
            'published_at' => $video->published_at?->toISOString(),
            'is_downloadable' => $video->is_downloadable,
            'sort_order' => $video->sort_order,
            'created_by_admin_id' => $video->created_by_admin_id,
            'updated_by_admin_id' => $video->updated_by_admin_id,
        ];
    }

    private function auditChanges(array $before, array $after): array
    {
        $oldValues = [];
        $newValues = [];
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));

        foreach ($keys as $key) {
            $oldValue = $before[$key] ?? null;
            $newValue = $after[$key] ?? null;

            if ($oldValue === $newValue) {
                continue;
            }

            $oldValues[$key] = $oldValue;
            $newValues[$key] = $newValue;
        }

        return [$oldValues, $newValues];
    }
}
