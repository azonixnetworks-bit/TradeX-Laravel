<?php

namespace App\Http\Controllers\Admin\Education;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Education\StoreAssignmentRequest;
use App\Http\Requests\Admin\Education\UpdateAssignmentPublicationRequest;
use App\Http\Requests\Admin\Education\UpdateAssignmentRequest;
use App\Models\AdminUser;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Services\Education\AssignmentMaterialStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class AssignmentController extends Controller
{
    public function __construct(
        private readonly AssignmentMaterialStorageService $materialStorage
    ) {
    }

    public function index(
        Course $course,
        CourseModule $module,
        Lesson $lesson
    ): View {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'education.view');
        $this->requireLessonOwnership($course, $module, $lesson);

        $assignments = $lesson
            ->assignments()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $stats = [
            'total' => $assignments->count(),
            'draft' => $assignments
                ->where('status', Assignment::STATUS_DRAFT)
                ->count(),
            'published' => $assignments
                ->where('status', Assignment::STATUS_PUBLISHED)
                ->count(),
            'archived' => $assignments
                ->where('status', Assignment::STATUS_ARCHIVED)
                ->count(),
            'required' => $assignments
                ->where('is_required', true)
                ->count(),
            'whatsapp' => $assignments
                ->where(
                    'submission_method',
                    Assignment::SUBMISSION_WHATSAPP
                )
                ->count(),
            'none' => $assignments
                ->where(
                    'submission_method',
                    Assignment::SUBMISSION_NONE
                )
                ->count(),
            'materials' => $assignments
                ->filter(
                    fn (Assignment $assignment): bool =>
                        $assignment->hasMaterial()
                )
                ->count(),
        ];

        $permissions = $this->educationPermissions($admin);

        return view(
            'admin.education.assignments.index',
            compact(
                'course',
                'module',
                'lesson',
                'assignments',
                'stats',
                'permissions'
            )
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
            'admin.education.assignments.create',
            compact('course', 'module', 'lesson')
        );
    }

    public function store(
        StoreAssignmentRequest $request,
        Course $course,
        CourseModule $module,
        Lesson $lesson
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'education.create');
        $this->requireLessonOwnership($course, $module, $lesson);

        $validated = $request->validated();
        $uploadedFile = $request->file('material_file');

        if (
            $uploadedFile !== null
            && ! $uploadedFile instanceof UploadedFile
        ) {
            throw ValidationException::withMessages([
                'material_file' =>
                    'The uploaded assignment material could not be read.',
            ]);
        }

        $assignmentUuid = Str::uuid()->toString();
        $storedMaterial = null;

        try {
            if ($uploadedFile instanceof UploadedFile) {
                $storedMaterial = $this->materialStorage->store(
                    $uploadedFile,
                    $assignmentUuid
                );
            }

            DB::transaction(
                function () use (
                    $course,
                    $module,
                    $lesson,
                    $validated,
                    $request,
                    $admin,
                    $assignmentUuid,
                    $storedMaterial
                ): void {
                    $lockedLesson = Lesson::query()
                        ->where(
                            'course_module_id',
                            $module->id
                        )
                        ->whereHas(
                            'module',
                            fn ($query) =>
                                $query->where(
                                    'course_id',
                                    $course->id
                                )
                        )
                        ->lockForUpdate()
                        ->findOrFail($lesson->id);

                    $assignment = Assignment::query()->create([
                        'uuid' => $assignmentUuid,
                        'lesson_id' => $lockedLesson->id,
                        'title' => $validated['title'],
                        'heading' => $validated['heading'] ?? null,
                        'description' => $validated['description'] ?? null,
                        'reference_image_path' =>
                            $validated['reference_image_path'] ?? null,
                        'material_disk' =>
                            $storedMaterial['disk'] ?? null,
                        'material_path' =>
                            $storedMaterial['path'] ?? null,
                        'material_original_name' =>
                            $storedMaterial['original_name'] ?? null,
                        'material_mime_type' =>
                            $storedMaterial['mime_type'] ?? null,
                        'material_size_bytes' =>
                            $storedMaterial['size'] ?? null,
                        'material_sha256' =>
                            $storedMaterial['sha256'] ?? null,
                        'material_uploaded_at' =>
                            $storedMaterial['uploaded_at'] ?? null,
                        'submission_method' =>
                            $validated['submission_method'],
                        'submission_instructions' =>
                            $validated['submission_instructions'] ?? null,
                        'is_required' =>
                            (bool) ($validated['is_required'] ?? true),
                        'status' => Assignment::STATUS_DRAFT,
                        'published_at' => null,
                        'sort_order' =>
                            (int) ($validated['sort_order'] ?? 0),
                        'created_by_admin_id' => $admin->id,
                        'updated_by_admin_id' => $admin->id,
                    ]);

                    AuditLog::record(
                        actorType: 'ADMIN',
                        actorId: $admin->id,
                        action: 'ASSIGNMENT_CREATED',
                        module: 'EDUCATION',
                        targetType: 'ASSIGNMENT',
                        targetId: $assignment->id,
                        newValues: $this->auditSnapshot($assignment),
                        ipAddress: $request->ip(),
                        userAgent: $request->userAgent()
                    );
                },
                3
            );
        } catch (Throwable $exception) {
            if (is_array($storedMaterial)) {
                $this->cleanupMaterial(
                    $storedMaterial['disk'] ?? null,
                    $storedMaterial['path'] ?? null,
                    'failed assignment create'
                );
            }

            throw $exception;
        }

        return redirect()
            ->route(
                'admin.education.courses.modules.lessons.assignments.index',
                [
                    'course' => $course,
                    'module' => $module,
                    'lesson' => $lesson,
                ]
            )
            ->with(
                'success',
                $storedMaterial !== null
                    ? 'Assignment created successfully as draft with protected admin material.'
                    : 'Assignment created successfully as draft.'
            );
    }

    public function edit(
        Course $course,
        CourseModule $module,
        Lesson $lesson,
        Assignment $assignment
    ): View {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'education.update');
        $this->requireAssignmentOwnership(
            $course,
            $module,
            $lesson,
            $assignment
        );

        return view(
            'admin.education.assignments.edit',
            compact('course', 'module', 'lesson', 'assignment')
        );
    }

    public function update(
        UpdateAssignmentRequest $request,
        Course $course,
        CourseModule $module,
        Lesson $lesson,
        Assignment $assignment
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'education.update');
        $this->requireAssignmentOwnership(
            $course,
            $module,
            $lesson,
            $assignment
        );

        $validated = $request->validated();
        $uploadedFile = $request->file('material_file');

        if (
            $uploadedFile !== null
            && ! $uploadedFile instanceof UploadedFile
        ) {
            throw ValidationException::withMessages([
                'material_file' =>
                    'The uploaded assignment material could not be read.',
            ]);
        }

        $removeMaterial = $request->boolean('remove_material');

        $oldMaterial = [
            'disk' => $assignment->material_disk,
            'path' => $assignment->material_path,
        ];

        $storedMaterial = null;

        try {
            if ($uploadedFile instanceof UploadedFile) {
                $storedMaterial = $this->materialStorage->store(
                    $uploadedFile,
                    $assignment->uuid
                );
            }

            DB::transaction(
                function () use (
                    $lesson,
                    $assignment,
                    $validated,
                    $request,
                    $admin,
                    $removeMaterial,
                    $storedMaterial
                ): void {
                    $lockedAssignment = Assignment::query()
                        ->where(
                            'lesson_id',
                            $lesson->id
                        )
                        ->lockForUpdate()
                        ->findOrFail($assignment->id);

                    $before = $this->auditSnapshot(
                        $lockedAssignment
                    );

                    $updates = [];

                    foreach ([
                        'title',
                        'heading',
                        'description',
                        'reference_image_path',
                        'submission_method',
                        'submission_instructions',
                        'is_required',
                        'sort_order',
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
                            'is_required',
                            $updates
                        )
                    ) {
                        $updates['is_required'] =
                            (bool) (
                                $updates['is_required']
                                ?? false
                            );
                    }

                    if (
                        array_key_exists(
                            'sort_order',
                            $updates
                        )
                    ) {
                        $updates['sort_order'] =
                            (int) (
                                $updates['sort_order']
                                ?? 0
                            );
                    }

                    if ($storedMaterial !== null) {
                        $updates = array_merge(
                            $updates,
                            [
                                'material_disk' =>
                                    $storedMaterial['disk'],
                                'material_path' =>
                                    $storedMaterial['path'],
                                'material_original_name' =>
                                    $storedMaterial['original_name'],
                                'material_mime_type' =>
                                    $storedMaterial['mime_type'],
                                'material_size_bytes' =>
                                    $storedMaterial['size'],
                                'material_sha256' =>
                                    $storedMaterial['sha256'],
                                'material_uploaded_at' =>
                                    $storedMaterial['uploaded_at'],
                            ]
                        );
                    } elseif ($removeMaterial) {
                        $updates = array_merge(
                            $updates,
                            [
                                'material_disk' => null,
                                'material_path' => null,
                                'material_original_name' => null,
                                'material_mime_type' => null,
                                'material_size_bytes' => null,
                                'material_sha256' => null,
                                'material_uploaded_at' => null,
                            ]
                        );
                    }

                    $updates['updated_by_admin_id'] =
                        $admin->id;

                    $lockedAssignment->fill($updates);

                    if (! $lockedAssignment->isDirty()) {
                        return;
                    }

                    $lockedAssignment->save();

                    [
                        $oldValues,
                        $newValues,
                    ] = $this->auditChanges(
                        $before,
                        $this->auditSnapshot(
                            $lockedAssignment->fresh()
                        )
                    );

                    AuditLog::record(
                        actorType: 'ADMIN',
                        actorId: $admin->id,
                        action: 'ASSIGNMENT_UPDATED',
                        module: 'EDUCATION',
                        targetType: 'ASSIGNMENT',
                        targetId: $lockedAssignment->id,
                        oldValues: $oldValues,
                        newValues: $newValues,
                        ipAddress: $request->ip(),
                        userAgent: $request->userAgent()
                    );
                },
                3
            );
        } catch (Throwable $exception) {
            if ($storedMaterial !== null) {
                $this->cleanupMaterial(
                    $storedMaterial['disk'] ?? null,
                    $storedMaterial['path'] ?? null,
                    'failed assignment update'
                );
            }

            throw $exception;
        }

        if (
            (
                $storedMaterial !== null
                || $removeMaterial
            )
            && (
                $oldMaterial['disk'] !== null
                || $oldMaterial['path'] !== null
            )
        ) {
            $newPath = $storedMaterial['path'] ?? null;

            if (
                $newPath === null
                || $newPath !== $oldMaterial['path']
            ) {
                $this->cleanupMaterial(
                    $oldMaterial['disk'],
                    $oldMaterial['path'],
                    $storedMaterial !== null
                        ? 'replaced assignment material'
                        : 'removed assignment material'
                );
            }
        }

        return redirect()
            ->route(
                'admin.education.courses.modules.lessons.assignments.index',
                [
                    'course' => $course,
                    'module' => $module,
                    'lesson' => $lesson,
                ]
            )
            ->with(
                'success',
                'Assignment updated successfully.'
            );
    }

    public function publication(
        UpdateAssignmentPublicationRequest $request,
        Course $course,
        CourseModule $module,
        Lesson $lesson,
        Assignment $assignment
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'education.publish');
        $this->requireAssignmentOwnership(
            $course,
            $module,
            $lesson,
            $assignment
        );

        $action = $request->publicationAction();
        $reason = $request->auditReason();

        $message = DB::transaction(
            function () use (
                $lesson,
                $assignment,
                $request,
                $admin,
                $action,
                $reason
            ): string {
                $lockedAssignment = Assignment::query()
                    ->where(
                        'lesson_id',
                        $lesson->id
                    )
                    ->lockForUpdate()
                    ->findOrFail($assignment->id);

                $before = $this->auditSnapshot(
                    $lockedAssignment
                );

                if (
                    $action
                    === UpdateAssignmentPublicationRequest::ACTION_PUBLISH
                ) {
                    abort_unless(
                        $lockedAssignment->status
                            === Assignment::STATUS_DRAFT,
                        422,
                        'Only a draft assignment can be published.'
                    );

                    $lockedAssignment->status =
                        Assignment::STATUS_PUBLISHED;

                    $lockedAssignment->published_at =
                        now();

                    $auditAction =
                        'ASSIGNMENT_PUBLISHED';

                    $message =
                        'Assignment published successfully.';
                } else {
                    abort_unless(
                        $lockedAssignment->status
                            === Assignment::STATUS_PUBLISHED,
                        422,
                        'Only a published assignment can be unpublished.'
                    );

                    $lockedAssignment->status =
                        Assignment::STATUS_DRAFT;

                    $lockedAssignment->published_at =
                        null;

                    $auditAction =
                        'ASSIGNMENT_UNPUBLISHED';

                    $message =
                        'Assignment unpublished successfully.';
                }

                $lockedAssignment->updated_by_admin_id =
                    $admin->id;

                $lockedAssignment->save();

                [
                    $oldValues,
                    $newValues,
                ] = $this->auditChanges(
                    $before,
                    $this->auditSnapshot(
                        $lockedAssignment->fresh()
                    )
                );

                if ($reason !== null) {
                    $newValues['reason'] = $reason;
                }

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->id,
                    action: $auditAction,
                    module: 'EDUCATION',
                    targetType: 'ASSIGNMENT',
                    targetId: $lockedAssignment->id,
                    oldValues: $oldValues,
                    newValues: $newValues,
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                return $message;
            },
            3
        );

        return redirect()
            ->route(
                'admin.education.courses.modules.lessons.assignments.index',
                [
                    'course' => $course,
                    'module' => $module,
                    'lesson' => $lesson,
                ]
            )
            ->with('success', $message);
    }

    public function destroy(
        Request $request,
        Course $course,
        CourseModule $module,
        Lesson $lesson,
        Assignment $assignment
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'education.delete');
        $this->requireAssignmentOwnership(
            $course,
            $module,
            $lesson,
            $assignment
        );

        $deletedMaterial = DB::transaction(
            function () use (
                $lesson,
                $assignment,
                $request,
                $admin
            ): array {
                $lockedAssignment = Assignment::query()
                    ->where(
                        'lesson_id',
                        $lesson->id
                    )
                    ->lockForUpdate()
                    ->findOrFail($assignment->id);

                abort_if(
                    $lockedAssignment->status
                        === Assignment::STATUS_PUBLISHED,
                    422,
                    'Unpublish the assignment before deleting it.'
                );

                $before = $this->auditSnapshot(
                    $lockedAssignment
                );

                $material = [
                    'disk' =>
                        $lockedAssignment->material_disk,
                    'path' =>
                        $lockedAssignment->material_path,
                ];

                $lockedAssignment->fill([
                    'material_disk' => null,
                    'material_path' => null,
                    'material_original_name' => null,
                    'material_mime_type' => null,
                    'material_size_bytes' => null,
                    'material_sha256' => null,
                    'material_uploaded_at' => null,
                    'updated_by_admin_id' => $admin->id,
                ]);

                $lockedAssignment->save();
                $lockedAssignment->delete();

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->id,
                    action: 'ASSIGNMENT_DELETED',
                    module: 'EDUCATION',
                    targetType: 'ASSIGNMENT',
                    targetId: $lockedAssignment->id,
                    oldValues: $before,
                    newValues: [
                        'deleted_at' =>
                            $lockedAssignment
                                ->deleted_at
                                ?->toISOString(),
                        'material_removed' =>
                            $material['path'] !== null,
                    ],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                return $material;
            },
            3
        );

        $this->cleanupMaterial(
            $deletedMaterial['disk'] ?? null,
            $deletedMaterial['path'] ?? null,
            'deleted assignment'
        );

        return redirect()
            ->route(
                'admin.education.courses.modules.lessons.assignments.index',
                [
                    'course' => $course,
                    'module' => $module,
                    'lesson' => $lesson,
                ]
            )
            ->with(
                'success',
                'Assignment deleted successfully.'
            );
    }

    private function requireAssignmentOwnership(
        Course $course,
        CourseModule $module,
        Lesson $lesson,
        Assignment $assignment
    ): void {
        $this->requireLessonOwnership(
            $course,
            $module,
            $lesson
        );

        abort_unless(
            (int) $assignment->lesson_id
                === (int) $lesson->id,
            404
        );
    }

    private function requireLessonOwnership(
        Course $course,
        CourseModule $module,
        Lesson $lesson
    ): void {
        abort_unless(
            (int) $module->course_id
                === (int) $course->id,
            404
        );

        abort_unless(
            (int) $lesson->course_module_id
                === (int) $module->id,
            404
        );
    }

    private function authenticatedAdmin(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless(
            $admin instanceof AdminUser,
            401
        );

        return $admin;
    }

    private function requirePermission(
        AdminUser $admin,
        string $permission
    ): void {
        abort_unless(
            $admin->hasPermission($permission),
            403
        );
    }

    private function educationPermissions(
        AdminUser $admin
    ): array {
        return [
            'view' =>
                $admin->hasPermission('education.view'),
            'create' =>
                $admin->hasPermission('education.create'),
            'update' =>
                $admin->hasPermission('education.update'),
            'delete' =>
                $admin->hasPermission('education.delete'),
            'publish' =>
                $admin->hasPermission('education.publish'),
        ];
    }

    private function auditSnapshot(
        Assignment $assignment
    ): array {
        return [
            'uuid' =>
                $assignment->uuid,
            'lesson_id' =>
                $assignment->lesson_id,
            'title' =>
                $assignment->title,
            'heading' =>
                $assignment->heading,
            'description_sha256' =>
                $assignment->description !== null
                    ? hash(
                        'sha256',
                        $assignment->description
                    )
                    : null,
            'description_length' =>
                $assignment->description !== null
                    ? mb_strlen(
                        $assignment->description
                    )
                    : 0,
            'reference_image_path_sha256' =>
                $assignment->reference_image_path !== null
                    ? hash(
                        'sha256',
                        $assignment->reference_image_path
                    )
                    : null,
            'has_material' =>
                $assignment->hasMaterial(),
            'material_disk' =>
                $assignment->material_disk,
            'material_path_sha256' =>
                $assignment->material_path !== null
                    ? hash(
                        'sha256',
                        $assignment->material_path
                    )
                    : null,
            'material_original_name_sha256' =>
                $assignment->material_original_name !== null
                    ? hash(
                        'sha256',
                        $assignment->material_original_name
                    )
                    : null,
            'material_mime_type' =>
                $assignment->material_mime_type,
            'material_size_bytes' =>
                $assignment->material_size_bytes,
            'material_sha256' =>
                $assignment->material_sha256,
            'material_uploaded_at' =>
                $assignment
                    ->material_uploaded_at
                    ?->toISOString(),
            'submission_method' =>
                $assignment->submission_method,
            'submission_instructions_sha256' =>
                $assignment->submission_instructions !== null
                    ? hash(
                        'sha256',
                        $assignment->submission_instructions
                    )
                    : null,
            'submission_instructions_length' =>
                $assignment->submission_instructions !== null
                    ? mb_strlen(
                        $assignment->submission_instructions
                    )
                    : 0,
            'is_required' =>
                $assignment->is_required,
            'status' =>
                $assignment->status,
            'published_at' =>
                $assignment
                    ->published_at
                    ?->toISOString(),
            'sort_order' =>
                $assignment->sort_order,
            'created_by_admin_id' =>
                $assignment->created_by_admin_id,
            'updated_by_admin_id' =>
                $assignment->updated_by_admin_id,
        ];
    }

    private function auditChanges(
        array $before,
        array $after
    ): array {
        $oldValues = [];
        $newValues = [];

        $keys = array_unique(
            array_merge(
                array_keys($before),
                array_keys($after)
            )
        );

        foreach ($keys as $key) {
            $oldValue = $before[$key] ?? null;
            $newValue = $after[$key] ?? null;

            if ($oldValue === $newValue) {
                continue;
            }

            $oldValues[$key] = $oldValue;
            $newValues[$key] = $newValue;
        }

        return [
            $oldValues,
            $newValues,
        ];
    }

    private function cleanupMaterial(
        ?string $disk,
        ?string $path,
        string $context
    ): void {
        if (
            $disk === null
            && $path === null
        ) {
            return;
        }

        try {
            $deleted = $this->materialStorage->delete(
                $disk,
                $path
            );

            if (! $deleted) {
                Log::warning(
                    'TradeX assignment material cleanup was refused or failed.',
                    [
                        'context' => $context,
                        'disk' => $disk,
                        'path_sha256' =>
                            $path !== null
                                ? hash(
                                    'sha256',
                                    $path
                                )
                                : null,
                    ]
                );
            }
        } catch (Throwable $exception) {
            Log::warning(
                'TradeX assignment material cleanup threw an exception.',
                [
                    'context' => $context,
                    'disk' => $disk,
                    'path_sha256' =>
                        $path !== null
                            ? hash(
                                'sha256',
                                $path
                            )
                            : null,
                    'error' =>
                        Str::limit(
                            $exception->getMessage(),
                            1000
                        ),
                ]
            );
        }
    }
}
