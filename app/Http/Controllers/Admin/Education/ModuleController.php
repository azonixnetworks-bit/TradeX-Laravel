<?php

namespace App\Http\Controllers\Admin\Education;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Education\StoreModuleRequest;
use App\Http\Requests\Admin\Education\UpdateModulePublicationRequest;
use App\Http\Requests\Admin\Education\UpdateModuleRequest;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseModule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ModuleController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Module Index
    |--------------------------------------------------------------------------
    */

    public function index(
        Course $course
    ): View {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'education.view'
        );

        $modules = $course
            ->modules()
            ->withCount('lessons')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $stats = [
            'total' =>
                $modules->count(),

            'draft' =>
                $modules
                    ->where(
                        'status',
                        CourseModule::STATUS_DRAFT
                    )
                    ->count(),

            'published' =>
                $modules
                    ->where(
                        'status',
                        CourseModule::STATUS_PUBLISHED
                    )
                    ->count(),

            'archived' =>
                $modules
                    ->where(
                        'status',
                        CourseModule::STATUS_ARCHIVED
                    )
                    ->count(),

            'lessons' =>
                (int) $modules
                    ->sum(
                        'lessons_count'
                    ),
        ];

        $permissions = $this->educationPermissions(
            $admin
        );

        return view(
            'admin.education.modules.index',
            compact(
                'course',
                'modules',
                'stats',
                'permissions'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Create Module Form
    |--------------------------------------------------------------------------
    */

    public function create(
        Course $course
    ): View {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'education.create'
        );

        return view(
            'admin.education.modules.create',
            compact(
                'course'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Store Module
    |--------------------------------------------------------------------------
    */

    public function store(
        StoreModuleRequest $request,
        Course $course
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'education.create'
        );

        $validated = $request->validated();

        $module = DB::transaction(
            function () use (
                $course,
                $validated,
                $request,
                $admin
            ): CourseModule {
                /*
                |--------------------------------------------------------------
                | Lock Parent Course
                |--------------------------------------------------------------
                |
                | Prevents a concurrent destructive parent mutation from racing
                | the module creation transaction.
                |
                */

                Course::query()
                    ->whereKey(
                        $course->id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $module = CourseModule::query()
                    ->create([
                        'course_id' =>
                            $course->id,

                        'code' =>
                            $validated['code'],

                        'slug' =>
                            $validated['slug'],

                        'title' =>
                            $validated['title'],

                        'short_description' =>
                            $validated['short_description']
                            ?? null,

                        'description' =>
                            $validated['description']
                            ?? null,

                        'status' =>
                            CourseModule::STATUS_DRAFT,

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
                        'MODULE_CREATED',

                    module:
                        'EDUCATION',

                    targetType:
                        'COURSE_MODULE',

                    targetId:
                        $module->id,

                    newValues:
                        $this->auditSnapshot(
                            $module
                        ),

                    ipAddress:
                        $request->ip(),

                    userAgent:
                        $request->userAgent()
                );

                return $module;
            }
        );

        return redirect()
            ->route(
                'admin.education.courses.modules.index',
                $course
            )
            ->with(
                'success',
                'Module created successfully as draft.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Edit Module Form
    |--------------------------------------------------------------------------
    */

    public function edit(
        Course $course,
        CourseModule $module
    ): View {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'education.update'
        );

        $this->requireModuleOwnership(
            $course,
            $module
        );

        $module->loadCount(
            'lessons'
        );

        return view(
            'admin.education.modules.edit',
            compact(
                'course',
                'module'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Update Module
    |--------------------------------------------------------------------------
    */

    public function update(
        UpdateModuleRequest $request,
        Course $course,
        CourseModule $module
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'education.update'
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
                $lockedModule = CourseModule::query()
                    ->where(
                        'course_id',
                        $course->id
                    )
                    ->lockForUpdate()
                    ->findOrFail(
                        $module->id
                    );

                $before = $this->auditSnapshot(
                    $lockedModule
                );

                $updates = [];

                foreach ([
                    'code',
                    'slug',
                    'title',
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

                $lockedModule->fill(
                    $updates
                );

                if (! $lockedModule->isDirty()) {
                    return;
                }

                $lockedModule->save();

                $after = $this->auditSnapshot(
                    $lockedModule->fresh()
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
                        'MODULE_UPDATED',

                    module:
                        'EDUCATION',

                    targetType:
                        'COURSE_MODULE',

                    targetId:
                        $lockedModule->id,

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
                'admin.education.courses.modules.index',
                $course
            )
            ->with(
                'success',
                'Module updated successfully.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Publish / Unpublish Module
    |--------------------------------------------------------------------------
    */

    public function publication(
        UpdateModulePublicationRequest $request,
        Course $course,
        CourseModule $module
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'education.publish'
        );

        $this->requireModuleOwnership(
            $course,
            $module
        );

        $action =
            $request->publicationAction();

        $reason =
            $request->auditReason();

        $message = DB::transaction(
            function () use (
                $course,
                $module,
                $request,
                $admin,
                $action,
                $reason
            ): string {
                $lockedModule = CourseModule::query()
                    ->where(
                        'course_id',
                        $course->id
                    )
                    ->lockForUpdate()
                    ->findOrFail(
                        $module->id
                    );

                $before = $this->auditSnapshot(
                    $lockedModule
                );

                if (
                    $action
                    === UpdateModulePublicationRequest::ACTION_PUBLISH
                ) {
                    abort_unless(
                        $lockedModule->status
                            === CourseModule::STATUS_DRAFT,
                        422,
                        'Only a draft module can be published.'
                    );

                    $lockedModule->status =
                        CourseModule::STATUS_PUBLISHED;

                    $lockedModule->published_at =
                        now();

                    $auditAction =
                        'MODULE_PUBLISHED';

                    $message =
                        'Module published successfully.';
                } else {
                    abort_unless(
                        $lockedModule->status
                            === CourseModule::STATUS_PUBLISHED,
                        422,
                        'Only a published module can be unpublished.'
                    );

                    $lockedModule->status =
                        CourseModule::STATUS_DRAFT;

                    $lockedModule->published_at =
                        null;

                    $auditAction =
                        'MODULE_UNPUBLISHED';

                    $message =
                        'Module unpublished successfully.';
                }

                $lockedModule->updated_by_admin_id =
                    $admin->id;

                $lockedModule->save();

                $after = $this->auditSnapshot(
                    $lockedModule->fresh()
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
                        'COURSE_MODULE',

                    targetId:
                        $lockedModule->id,

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
                'admin.education.courses.modules.index',
                $course
            )
            ->with(
                'success',
                $message
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Delete Module
    |--------------------------------------------------------------------------
    |
    | Soft-delete only.
    |
    | A published module must first be unpublished.
    |
    | A module containing lessons is also protected from deletion because a
    | parent soft-delete would not automatically soft-delete lesson rows.
    | This prevents hidden/orphan-like curriculum records.
    |
    */

    public function destroy(
        Request $request,
        Course $course,
        CourseModule $module
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'education.delete'
        );

        $this->requireModuleOwnership(
            $course,
            $module
        );

        DB::transaction(
            function () use (
                $course,
                $module,
                $request,
                $admin
            ): void {
                $lockedModule = CourseModule::query()
                    ->where(
                        'course_id',
                        $course->id
                    )
                    ->lockForUpdate()
                    ->findOrFail(
                        $module->id
                    );

                abort_if(
                    $lockedModule->status
                        === CourseModule::STATUS_PUBLISHED,
                    422,
                    'Unpublish the module before deleting it.'
                );

                abort_if(
                    $lockedModule
                        ->lessons()
                        ->exists(),
                    422,
                    'Remove or relocate all lessons before deleting this module.'
                );

                $before = $this->auditSnapshot(
                    $lockedModule
                );

                $lockedModule->updated_by_admin_id =
                    $admin->id;

                $lockedModule->save();

                $lockedModule->delete();

                AuditLog::record(
                    actorType:
                        'ADMIN',

                    actorId:
                        $admin->id,

                    action:
                        'MODULE_DELETED',

                    module:
                        'EDUCATION',

                    targetType:
                        'COURSE_MODULE',

                    targetId:
                        $lockedModule->id,

                    oldValues:
                        $before,

                    newValues: [
                        'deleted_at' =>
                            $lockedModule
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
                'admin.education.courses.modules.index',
                $course
            )
            ->with(
                'success',
                'Module deleted successfully.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | ADMIN-03 — Nested Ownership Boundary
    |--------------------------------------------------------------------------
    |
    | Future routes will also use scopeBindings(). This explicit check is kept
    | as defence-in-depth so a module from Course B cannot be mutated through
    | a Course A URL even if route configuration regresses.
    |
    */

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
    */

    private function auditSnapshot(
        CourseModule $module
    ): array {
        $description =
            $module->description;

        return [
            'uuid' =>
                $module->uuid,

            'course_id' =>
                $module->course_id,

            'code' =>
                $module->code,

            'slug' =>
                $module->slug,

            'title' =>
                $module->title,

            'short_description' =>
                $module->short_description,

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

            'status' =>
                $module->status,

            'published_at' =>
                $module
                    ->published_at
                    ?->toISOString(),

            'sort_order' =>
                $module->sort_order,

            'created_by_admin_id' =>
                $module->created_by_admin_id,

            'updated_by_admin_id' =>
                $module->updated_by_admin_id,
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