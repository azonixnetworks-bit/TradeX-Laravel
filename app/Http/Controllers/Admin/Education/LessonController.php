<?php

namespace App\Http\Controllers\Admin\Education;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Education\StoreLessonRequest;
use App\Http\Requests\Admin\Education\UpdateLessonPublicationRequest;
use App\Http\Requests\Admin\Education\UpdateLessonRequest;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LessonController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Lesson Index
    |--------------------------------------------------------------------------
    */

    public function index(
        Course $course,
        CourseModule $module
    ): View {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'education.view'
        );

        $this->requireModuleOwnership(
            $course,
            $module
        );

        $lessons = $module
            ->lessons()
            ->withCount([
                'videos',
                'assignments',
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $stats = [
            'total' =>
                $lessons->count(),

            'draft' =>
                $lessons
                    ->where(
                        'status',
                        Lesson::STATUS_DRAFT
                    )
                    ->count(),

            'published' =>
                $lessons
                    ->where(
                        'status',
                        Lesson::STATUS_PUBLISHED
                    )
                    ->count(),

            'archived' =>
                $lessons
                    ->where(
                        'status',
                        Lesson::STATUS_ARCHIVED
                    )
                    ->count(),

            'preview' =>
                $lessons
                    ->where(
                        'is_preview',
                        true
                    )
                    ->count(),

            'videos' =>
                (int) $lessons
                    ->sum(
                        'videos_count'
                    ),

            'assignments' =>
                (int) $lessons
                    ->sum(
                        'assignments_count'
                    ),
        ];

        $permissions = $this->educationPermissions(
            $admin
        );

        return view(
            'admin.education.lessons.index',
            compact(
                'course',
                'module',
                'lessons',
                'stats',
                'permissions'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Create Lesson Form
    |--------------------------------------------------------------------------
    */

    public function create(
        Course $course,
        CourseModule $module
    ): View {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'education.create'
        );

        $this->requireModuleOwnership(
            $course,
            $module
        );

        return view(
            'admin.education.lessons.create',
            compact(
                'course',
                'module'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Store Lesson
    |--------------------------------------------------------------------------
    */

    public function store(
        StoreLessonRequest $request,
        Course $course,
        CourseModule $module
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'education.create'
        );

        $this->requireModuleOwnership(
            $course,
            $module
        );

        $validated = $request->validated();

        DB::transaction(
            function () use (
                $course,
                $module,
                $validated,
                $request,
                $admin
            ): void {
                /*
                |--------------------------------------------------------------
                | Lock Parent Module
                |--------------------------------------------------------------
                |
                | The parent module is re-resolved inside the transaction and
                | locked while the lesson is created. The course predicate is
                | retained so a cross-course module cannot be accepted here.
                |
                */

                $lockedModule = CourseModule::query()
                    ->where(
                        'course_id',
                        $course->id
                    )
                    ->lockForUpdate()
                    ->findOrFail(
                        $module->id
                    );

                $lesson = Lesson::query()
                    ->create([
                        'course_module_id' =>
                            $lockedModule->id,

                        'code' =>
                            $validated['code'],

                        'slug' =>
                            $validated['slug'],

                        'title' =>
                            $validated['title'],

                        'heading' =>
                            $validated['heading']
                            ?? null,

                        'short_description' =>
                            $validated['short_description']
                            ?? null,

                        'description' =>
                            $validated['description']
                            ?? null,

                        'is_preview' =>
                            (bool) (
                                $validated['is_preview']
                                ?? false
                            ),

                        'status' =>
                            Lesson::STATUS_DRAFT,

                        'published_at' =>
                            null,

                        'sort_order' =>
                            (int) (
                                $validated['sort_order']
                                ?? 0
                            ),

                        'created_by_admin_id' =>
                            $admin->id,

                        'updated_by_admin_id' =>
                            $admin->id,
                    ]);

                AuditLog::record(
                    actorType:
                        'ADMIN',

                    actorId:
                        $admin->id,

                    action:
                        'LESSON_CREATED',

                    module:
                        'EDUCATION',

                    targetType:
                        'LESSON',

                    targetId:
                        $lesson->id,

                    newValues:
                        $this->auditSnapshot(
                            $lesson
                        ),

                    ipAddress:
                        $request->ip(),

                    userAgent:
                        $request->userAgent()
                );
            }
        );

        return redirect()
            ->route(
                'admin.education.courses.modules.lessons.index',
                [
                    'course' => $course,
                    'module' => $module,
                ]
            )
            ->with(
                'success',
                'Lesson created successfully as draft.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Edit Lesson Form
    |--------------------------------------------------------------------------
    */

    public function edit(
        Course $course,
        CourseModule $module,
        Lesson $lesson
    ): View {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'education.update'
        );

        $this->requireNestedOwnership(
            $course,
            $module,
            $lesson
        );

        $lesson->loadCount([
            'videos',
            'assignments',
        ]);

        return view(
            'admin.education.lessons.edit',
            compact(
                'course',
                'module',
                'lesson'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Update Lesson
    |--------------------------------------------------------------------------
    */

    public function update(
        UpdateLessonRequest $request,
        Course $course,
        CourseModule $module,
        Lesson $lesson
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'education.update'
        );

        $this->requireNestedOwnership(
            $course,
            $module,
            $lesson
        );

        $validated = $request->validated();

        DB::transaction(
            function () use (
                $module,
                $lesson,
                $validated,
                $request,
                $admin
            ): void {
                $lockedLesson = Lesson::query()
                    ->where(
                        'course_module_id',
                        $module->id
                    )
                    ->lockForUpdate()
                    ->findOrFail(
                        $lesson->id
                    );

                $before = $this->auditSnapshot(
                    $lockedLesson
                );

                $updates = [];

                foreach ([
                    'code',
                    'slug',
                    'title',
                    'heading',
                    'short_description',
                    'description',
                ] as $field) {
                    if (
                        array_key_exists(
                            $field,
                            $validated
                        )
                    ) {
                        $updates[$field] =
                            $validated[$field];
                    }
                }

                if (
                    array_key_exists(
                        'is_preview',
                        $validated
                    )
                ) {
                    $updates['is_preview'] =
                        (bool) (
                            $validated['is_preview']
                            ?? false
                        );
                }

                if (
                    array_key_exists(
                        'sort_order',
                        $validated
                    )
                ) {
                    $updates['sort_order'] =
                        (int) (
                            $validated['sort_order']
                            ?? 0
                        );
                }

                $updates['updated_by_admin_id'] =
                    $admin->id;

                $lockedLesson->fill(
                    $updates
                );

                if (! $lockedLesson->isDirty()) {
                    return;
                }

                $lockedLesson->save();

                $after = $this->auditSnapshot(
                    $lockedLesson->fresh()
                );

                [
                    $oldValues,
                    $newValues,
                ] = $this->auditChanges(
                    $before,
                    $after
                );

                AuditLog::record(
                    actorType:
                        'ADMIN',

                    actorId:
                        $admin->id,

                    action:
                        'LESSON_UPDATED',

                    module:
                        'EDUCATION',

                    targetType:
                        'LESSON',

                    targetId:
                        $lockedLesson->id,

                    oldValues:
                        $oldValues,

                    newValues:
                        $newValues,

                    ipAddress:
                        $request->ip(),

                    userAgent:
                        $request->userAgent()
                );
            }
        );

        return redirect()
            ->route(
                'admin.education.courses.modules.lessons.index',
                [
                    'course' => $course,
                    'module' => $module,
                ]
            )
            ->with(
                'success',
                'Lesson updated successfully.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Publish / Unpublish Lesson
    |--------------------------------------------------------------------------
    */

    public function publication(
        UpdateLessonPublicationRequest $request,
        Course $course,
        CourseModule $module,
        Lesson $lesson
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'education.publish'
        );

        $this->requireNestedOwnership(
            $course,
            $module,
            $lesson
        );

        $action =
            $request->publicationAction();

        $reason =
            $request->auditReason();

        $message = DB::transaction(
            function () use (
                $module,
                $lesson,
                $request,
                $admin,
                $action,
                $reason
            ): string {
                $lockedLesson = Lesson::query()
                    ->where(
                        'course_module_id',
                        $module->id
                    )
                    ->lockForUpdate()
                    ->findOrFail(
                        $lesson->id
                    );

                $before = $this->auditSnapshot(
                    $lockedLesson
                );

                if (
                    $action
                    === UpdateLessonPublicationRequest::ACTION_PUBLISH
                ) {
                    abort_unless(
                        $lockedLesson->status
                            === Lesson::STATUS_DRAFT,
                        422,
                        'Only a draft lesson can be published.'
                    );

                    $lockedLesson->status =
                        Lesson::STATUS_PUBLISHED;

                    $lockedLesson->published_at =
                        now();

                    $auditAction =
                        'LESSON_PUBLISHED';

                    $message =
                        'Lesson published successfully.';
                } else {
                    abort_unless(
                        $lockedLesson->status
                            === Lesson::STATUS_PUBLISHED,
                        422,
                        'Only a published lesson can be unpublished.'
                    );

                    $lockedLesson->status =
                        Lesson::STATUS_DRAFT;

                    $lockedLesson->published_at =
                        null;

                    $auditAction =
                        'LESSON_UNPUBLISHED';

                    $message =
                        'Lesson unpublished successfully.';
                }

                $lockedLesson->updated_by_admin_id =
                    $admin->id;

                $lockedLesson->save();

                $after = $this->auditSnapshot(
                    $lockedLesson->fresh()
                );

                [
                    $oldValues,
                    $newValues,
                ] = $this->auditChanges(
                    $before,
                    $after
                );

                if ($reason !== null) {
                    $newValues['reason'] =
                        $reason;
                }

                AuditLog::record(
                    actorType:
                        'ADMIN',

                    actorId:
                        $admin->id,

                    action:
                        $auditAction,

                    module:
                        'EDUCATION',

                    targetType:
                        'LESSON',

                    targetId:
                        $lockedLesson->id,

                    oldValues:
                        $oldValues,

                    newValues:
                        $newValues,

                    ipAddress:
                        $request->ip(),

                    userAgent:
                        $request->userAgent()
                );

                return $message;
            }
        );

        return redirect()
            ->route(
                'admin.education.courses.modules.lessons.index',
                [
                    'course' => $course,
                    'module' => $module,
                ]
            )
            ->with(
                'success',
                $message
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Delete Lesson
    |--------------------------------------------------------------------------
    |
    | Soft-delete only.
    |
    | A published lesson must first be unpublished.
    |
    | A lesson containing videos or assignments is protected from deletion.
    | Parent soft-delete does not automatically soft-delete those children, so
    | this guard prevents hidden curriculum/media records from remaining active.
    |
    */

    public function destroy(
        Request $request,
        Course $course,
        CourseModule $module,
        Lesson $lesson
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'education.delete'
        );

        $this->requireNestedOwnership(
            $course,
            $module,
            $lesson
        );

        DB::transaction(
            function () use (
                $module,
                $lesson,
                $request,
                $admin
            ): void {
                $lockedLesson = Lesson::query()
                    ->where(
                        'course_module_id',
                        $module->id
                    )
                    ->lockForUpdate()
                    ->findOrFail(
                        $lesson->id
                    );

                abort_if(
                    $lockedLesson->status
                        === Lesson::STATUS_PUBLISHED,
                    422,
                    'Unpublish the lesson before deleting it.'
                );

                abort_if(
                    $lockedLesson
                        ->videos()
                        ->exists(),
                    422,
                    'Remove all lesson videos before deleting this lesson.'
                );

                abort_if(
                    $lockedLesson
                        ->assignments()
                        ->exists(),
                    422,
                    'Remove all assignments before deleting this lesson.'
                );

                $before = $this->auditSnapshot(
                    $lockedLesson
                );

                $lockedLesson->updated_by_admin_id =
                    $admin->id;

                $lockedLesson->save();

                $lockedLesson->delete();

                AuditLog::record(
                    actorType:
                        'ADMIN',

                    actorId:
                        $admin->id,

                    action:
                        'LESSON_DELETED',

                    module:
                        'EDUCATION',

                    targetType:
                        'LESSON',

                    targetId:
                        $lockedLesson->id,

                    oldValues:
                        $before,

                    newValues: [
                        'deleted_at' =>
                            $lockedLesson
                                ->deleted_at
                                ?->toISOString(),
                    ],

                    ipAddress:
                        $request->ip(),

                    userAgent:
                        $request->userAgent()
                );
            }
        );

        return redirect()
            ->route(
                'admin.education.courses.modules.lessons.index',
                [
                    'course' => $course,
                    'module' => $module,
                ]
            )
            ->with(
                'success',
                'Lesson deleted successfully.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | ADMIN-03 — Nested Ownership Boundary
    |--------------------------------------------------------------------------
    |
    | Lesson member routes will use scopeBindings(). These explicit checks are
    | retained as defence-in-depth so a Module or Lesson from another parent
    | cannot be accessed through a mismatched nested URL if route configuration
    | is changed accidentally later.
    |
    */

    private function requireNestedOwnership(
        Course $course,
        CourseModule $module,
        Lesson $lesson
    ): void {
        $this->requireModuleOwnership(
            $course,
            $module
        );

        abort_unless(
            (int) $lesson->course_module_id
                === (int) $module->id,
            404
        );
    }


    private function requireModuleOwnership(
        Course $course,
        CourseModule $module
    ): void {
        abort_unless(
            (int) $module->course_id
                === (int) $course->id,
            404
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ADMIN-03 — Authenticated Admin Resolver
    |--------------------------------------------------------------------------
    */

    private function authenticatedAdmin(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless(
            $admin instanceof AdminUser,
            401
        );

        return $admin;
    }


    /*
    |--------------------------------------------------------------------------
    | ADMIN-03 — Permission Boundary
    |--------------------------------------------------------------------------
    */

    private function requirePermission(
        AdminUser $admin,
        string $permission
    ): void {
        abort_unless(
            $admin->hasPermission(
                $permission
            ),
            403
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Education Permission Map
    |--------------------------------------------------------------------------
    */

    private function educationPermissions(
        AdminUser $admin
    ): array {
        return [
            'view' =>
                $admin->hasPermission(
                    'education.view'
                ),

            'create' =>
                $admin->hasPermission(
                    'education.create'
                ),

            'update' =>
                $admin->hasPermission(
                    'education.update'
                ),

            'delete' =>
                $admin->hasPermission(
                    'education.delete'
                ),

            'publish' =>
                $admin->hasPermission(
                    'education.publish'
                ),
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Audit Snapshot
    |--------------------------------------------------------------------------
    |
    | Raw long-form lesson description is not written into audit logs. A hash
    | and character length are stored instead, matching the existing Education
    | audit pattern while still making content changes detectable.
    |
    */

    private function auditSnapshot(
        Lesson $lesson
    ): array {
        $description =
            $lesson->description;

        return [
            'uuid' =>
                $lesson->uuid,

            'course_module_id' =>
                $lesson->course_module_id,

            'code' =>
                $lesson->code,

            'slug' =>
                $lesson->slug,

            'title' =>
                $lesson->title,

            'heading' =>
                $lesson->heading,

            'short_description' =>
                $lesson->short_description,

            'description_sha256' =>
                $description !== null
                    ? hash(
                        'sha256',
                        $description
                    )
                    : null,

            'description_length' =>
                $description !== null
                    ? mb_strlen(
                        $description
                    )
                    : 0,

            'is_preview' =>
                (bool) $lesson->is_preview,

            'status' =>
                $lesson->status,

            'published_at' =>
                $lesson
                    ->published_at
                    ?->toISOString(),

            'sort_order' =>
                $lesson->sort_order,

            'created_by_admin_id' =>
                $lesson->created_by_admin_id,

            'updated_by_admin_id' =>
                $lesson->updated_by_admin_id,
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Audit Change Extraction
    |--------------------------------------------------------------------------
    */

    private function auditChanges(
        array $before,
        array $after
    ): array {
        $oldValues = [];
        $newValues = [];

        $keys = array_unique(
            array_merge(
                array_keys(
                    $before
                ),
                array_keys(
                    $after
                )
            )
        );

        foreach ($keys as $key) {
            $oldValue =
                $before[$key]
                ?? null;

            $newValue =
                $after[$key]
                ?? null;

            if ($oldValue === $newValue) {
                continue;
            }

            $oldValues[$key] =
                $oldValue;

            $newValues[$key] =
                $newValue;
        }

        return [
            $oldValues,
            $newValues,
        ];
    }
}