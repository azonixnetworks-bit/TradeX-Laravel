<?php

namespace App\Http\Controllers\Admin\Education;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Education\StoreCourseRequest;
use App\Http\Requests\Admin\Education\UpdateCoursePublicationRequest;
use App\Http\Requests\Admin\Education\UpdateCourseRequest;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Course;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class CourseController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Courses Index
    |--------------------------------------------------------------------------
    */

    public function index(
        Request $request
    ): View {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'education.view'
        );

        $filters = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],

            'status' => [
                'nullable',
                Rule::in([
                    Course::STATUS_DRAFT,
                    Course::STATUS_PUBLISHED,
                    Course::STATUS_ARCHIVED,
                ]),
            ],

            'access_level' => [
                'nullable',
                Rule::in([
                    Course::ACCESS_FREE,
                    Course::ACCESS_PREMIUM,
                ]),
            ],

            'language_code' => [
                'nullable',
                'string',
                'max:10',
                'regex:/^[a-zA-Z]{2,3}(?:[-_][a-zA-Z]{2})?$/',
            ],

            'sort' => [
                'nullable',
                Rule::in([
                    'newest',
                    'oldest',
                    'title_asc',
                    'title_desc',
                    'display_order',
                ]),
            ],

            'per_page' => [
                'nullable',
                'integer',
                Rule::in([
                    10,
                    20,
                    50,
                    100,
                ]),
            ],
        ]);

        $search = trim(
            (string) ($filters['search'] ?? '')
        );

        $status = $filters['status'] ?? null;

        $accessLevel =
            $filters['access_level']
            ?? null;

        $languageCode =
            isset($filters['language_code'])
                ? $this->normalizeLanguageCode(
                    (string) $filters['language_code']
                )
                : null;

        $sort =
            $filters['sort']
            ?? 'display_order';

        $perPage =
            (int) (
                $filters['per_page']
                ?? 20
            );

        $query = Course::query()
            ->withCount('modules');

        if ($search !== '') {
            $query->where(
                function (
                    Builder $query
                ) use ($search): void {
                    $query
                        ->where(
                            'title',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhere(
                            'code',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhere(
                            'slug',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhere(
                            'uuid',
                            $search
                        );
                }
            );
        }

        if ($status !== null) {
            $query->where(
                'status',
                $status
            );
        }

        if ($accessLevel !== null) {
            $query->where(
                'access_level',
                $accessLevel
            );
        }

        if ($languageCode !== null) {
            $query->where(
                'language_code',
                $languageCode
            );
        }

        match ($sort) {
            'newest' => $query->orderByDesc(
                'created_at'
            ),

            'oldest' => $query->orderBy(
                'created_at'
            ),

            'title_asc' => $query->orderBy(
                'title'
            ),

            'title_desc' => $query->orderByDesc(
                'title'
            ),

            default => $query
                ->orderBy(
                    'sort_order'
                )
                ->orderBy(
                    'id'
                ),
        };

        $courses = $query
            ->paginate(
                $perPage
            )
            ->withQueryString();

        $stats = [
            'total' => Course::query()
                ->count(),

            'draft' => Course::query()
                ->where(
                    'status',
                    Course::STATUS_DRAFT
                )
                ->count(),

            'published' => Course::query()
                ->where(
                    'status',
                    Course::STATUS_PUBLISHED
                )
                ->count(),

            'archived' => Course::query()
                ->where(
                    'status',
                    Course::STATUS_ARCHIVED
                )
                ->count(),

            'free' => Course::query()
                ->where(
                    'access_level',
                    Course::ACCESS_FREE
                )
                ->count(),

            'premium' => Course::query()
                ->where(
                    'access_level',
                    Course::ACCESS_PREMIUM
                )
                ->count(),
        ];

        $permissions = $this->educationPermissions(
            $admin
        );

        return view(
            'admin.education.courses.index',
            compact(
                'courses',
                'stats',
                'filters',
                'permissions'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Create Course Form
    |--------------------------------------------------------------------------
    */

    public function create(): View
    {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'education.create'
        );

        $accessLevels = [
            Course::ACCESS_FREE,
            Course::ACCESS_PREMIUM,
        ];

        return view(
            'admin.education.courses.create',
            compact(
                'accessLevels'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Store Course
    |--------------------------------------------------------------------------
    */

    public function store(
        StoreCourseRequest $request
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'education.create'
        );

        $validated = $request->validated();

        $courseUuid = (string) Str::uuid();
        $thumbnailUpload = $request->file(
            'thumbnail_image'
        );
        $uploadedThumbnailPath =
            $thumbnailUpload instanceof UploadedFile
                ? $this->storeCourseThumbnail(
                    $thumbnailUpload,
                    $courseUuid
                )
                : null;
        $thumbnailPath =
            $uploadedThumbnailPath
            ?? ($validated['thumbnail_path'] ?? null);

        try {
            $course = DB::transaction(
                function () use (
                    $validated,
                    $request,
                    $admin,
                    $courseUuid,
                    $thumbnailPath
                ): Course {
                    $course = Course::create([
                        'uuid' => $courseUuid,

                        'code' => $validated['code'],

                        'slug' => $validated['slug'],

                        'title' => $validated['title'],

                        'short_description' => $validated['short_description']
                            ?? null,

                        'description' => $validated['description']
                            ?? null,

                        'thumbnail_path' => $thumbnailPath,

                        'language_code' => $validated['language_code'],

                        'access_level' => $validated['access_level'],

                        'status' => Course::STATUS_DRAFT,

                        'published_at' => null,

                        'sort_order' => (int) (
                            $validated['sort_order']
                            ?? 0
                        ),

                        'is_featured' => $request->boolean(
                            'is_featured'
                        ),

                        'created_by_admin_id' => $admin->id,

                        'updated_by_admin_id' => $admin->id,
                    ]);

                    AuditLog::record(
                        actorType: 'ADMIN',

                        actorId: $admin->id,

                        action: 'COURSE_CREATED',

                        module: 'EDUCATION',

                        targetType: 'COURSE',

                        targetId: $course->id,

                        newValues: $this->auditSnapshot(
                            $course
                        ),

                        ipAddress: $request->ip(),

                        userAgent: $request->userAgent()
                    );

                    return $course;
                }
            );
        } catch (Throwable $exception) {
            if ($uploadedThumbnailPath !== null) {
                $this->deleteManagedCourseThumbnail(
                    $uploadedThumbnailPath,
                    $courseUuid
                );
            }

            throw $exception;
        }

        return redirect()
            ->route(
                'admin.education.courses.show',
                $course
            )
            ->with(
                'success',
                'Course created successfully as draft.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Course Details
    |--------------------------------------------------------------------------
    */

    public function show(
        Course $course
    ): View {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'education.view'
        );

        $course->load([
            'createdByAdmin',
            'updatedByAdmin',

            'modules' => function ($query): void {
                $query
                    ->withCount(
                        'lessons'
                    )
                    ->orderBy(
                        'sort_order'
                    )
                    ->orderBy(
                        'id'
                    );
            },
        ]);

        AuditLog::record(
            actorType: 'ADMIN',

            actorId: $admin->id,

            action: 'COURSE_VIEWED',

            module: 'EDUCATION',

            targetType: 'COURSE',

            targetId: $course->id,

            ipAddress: request()->ip(),

            userAgent: request()->userAgent()
        );

        $permissions = $this->educationPermissions(
            $admin
        );

        return view(
            'admin.education.courses.show',
            compact(
                'course',
                'permissions'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Edit Course Form
    |--------------------------------------------------------------------------
    */

    public function edit(
        Course $course
    ): View {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'education.update'
        );

        $accessLevels = [
            Course::ACCESS_FREE,
            Course::ACCESS_PREMIUM,
        ];

        return view(
            'admin.education.courses.edit',
            compact(
                'course',
                'accessLevels'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update Course
    |--------------------------------------------------------------------------
    */

    public function update(
        UpdateCourseRequest $request,
        Course $course
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'education.update'
        );

        $validated = $request->validated();

        $thumbnailUpload = $request->file(
            'thumbnail_image'
        );
        $uploadedThumbnailPath =
            $thumbnailUpload instanceof UploadedFile
                ? $this->storeCourseThumbnail(
                    $thumbnailUpload,
                    $course->uuid
                )
                : null;
        $previousThumbnailPath = null;

        try {
            DB::transaction(
                function () use (
                    $course,
                    $validated,
                    $request,
                    $admin,
                    $uploadedThumbnailPath,
                    &$previousThumbnailPath
                ): void {
                    $lockedCourse = Course::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $course->id
                        );

                    $before = $this->auditSnapshot(
                        $lockedCourse
                    );

                    $updates = [];

                    foreach ([
                        'code',
                        'slug',
                        'title',
                        'short_description',
                        'description',
                        'language_code',
                        'access_level',
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

                    if ($uploadedThumbnailPath !== null) {
                        $previousThumbnailPath =
                            $lockedCourse->thumbnail_path;
                        $updates['thumbnail_path'] =
                            $uploadedThumbnailPath;
                    } elseif (
                        $request->boolean(
                            'remove_thumbnail'
                        )
                    ) {
                        $previousThumbnailPath =
                            $lockedCourse->thumbnail_path;
                        $updates['thumbnail_path'] = null;
                    } elseif (
                        array_key_exists(
                            'thumbnail_path',
                            $validated
                        )
                    ) {
                        $previousThumbnailPath =
                            $lockedCourse->thumbnail_path;
                        $updates['thumbnail_path'] =
                            $validated['thumbnail_path'];
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

                    if (
                        array_key_exists(
                            'is_featured',
                            $validated
                        )
                    ) {
                        $updates['is_featured'] =
                            $request->boolean(
                                'is_featured'
                            );
                    }

                    $updates['updated_by_admin_id'] =
                        $admin->id;

                    $lockedCourse->fill(
                        $updates
                    );

                    if (! $lockedCourse->isDirty()) {
                        return;
                    }

                    $lockedCourse->save();

                    $after = $this->auditSnapshot(
                        $lockedCourse->fresh()
                    );

                    [
                        $oldValues,
                        $newValues,
                    ] = $this->auditChanges(
                        $before,
                        $after
                    );

                    AuditLog::record(
                        actorType: 'ADMIN',

                        actorId: $admin->id,

                        action: 'COURSE_UPDATED',

                        module: 'EDUCATION',

                        targetType: 'COURSE',

                        targetId: $lockedCourse->id,

                        oldValues: $oldValues,

                        newValues: $newValues,

                        ipAddress: $request->ip(),

                        userAgent: $request->userAgent()
                    );
                }
            );
        } catch (Throwable $exception) {
            if ($uploadedThumbnailPath !== null) {
                $this->deleteManagedCourseThumbnail(
                    $uploadedThumbnailPath,
                    $course->uuid
                );
            }

            throw $exception;
        }

        if (
            $previousThumbnailPath !== null
            && $previousThumbnailPath
                !== $uploadedThumbnailPath
        ) {
            $this->deleteManagedCourseThumbnail(
                $previousThumbnailPath,
                $course->uuid
            );
        }

        return redirect()
            ->route(
                'admin.education.courses.show',
                $course
            )
            ->with(
                'success',
                'Course updated successfully.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Publish / Unpublish Course
    |--------------------------------------------------------------------------
    */

    public function publication(
        UpdateCoursePublicationRequest $request,
        Course $course
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'education.publish'
        );

        $action =
            $request->publicationAction();

        $reason =
            $request->auditReason();

        $message = DB::transaction(
            function () use (
                $course,
                $request,
                $admin,
                $action,
                $reason
            ): string {
                $lockedCourse = Course::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $course->id
                    );

                $before = $this->auditSnapshot(
                    $lockedCourse
                );

                if (
                    $action
                    === UpdateCoursePublicationRequest::ACTION_PUBLISH
                ) {
                    abort_unless(
                        $lockedCourse->status
                            === Course::STATUS_DRAFT,
                        422,
                        'Only a draft course can be published.'
                    );

                    $lockedCourse->status =
                        Course::STATUS_PUBLISHED;

                    $lockedCourse->published_at =
                        now();

                    $auditAction =
                        'COURSE_PUBLISHED';

                    $message =
                        'Course published successfully.';
                } else {
                    abort_unless(
                        $lockedCourse->status
                            === Course::STATUS_PUBLISHED,
                        422,
                        'Only a published course can be unpublished.'
                    );

                    $lockedCourse->status =
                        Course::STATUS_DRAFT;

                    $lockedCourse->published_at =
                        null;

                    $auditAction =
                        'COURSE_UNPUBLISHED';

                    $message =
                        'Course unpublished successfully.';
                }

                $lockedCourse->updated_by_admin_id =
                    $admin->id;

                $lockedCourse->save();

                $after = $this->auditSnapshot(
                    $lockedCourse->fresh()
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
                    actorType: 'ADMIN',

                    actorId: $admin->id,

                    action: $auditAction,

                    module: 'EDUCATION',

                    targetType: 'COURSE',

                    targetId: $lockedCourse->id,

                    oldValues: $oldValues,

                    newValues: $newValues,

                    ipAddress: $request->ip(),

                    userAgent: $request->userAgent()
                );

                return $message;
            }
        );

        return redirect()
            ->route(
                'admin.education.courses.show',
                $course
            )
            ->with(
                'success',
                $message
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Course
    |--------------------------------------------------------------------------
    |
    | Course deletion is a soft delete. Published courses must be explicitly
    | unpublished first so deletion cannot silently bypass the publication
    | lifecycle.
    |
    */

    public function destroy(
        Request $request,
        Course $course
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'education.delete'
        );

        DB::transaction(
            function () use (
                $course,
                $request,
                $admin
            ): void {
                $lockedCourse = Course::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $course->id
                    );

                abort_if(
                    $lockedCourse->status
                        === Course::STATUS_PUBLISHED,
                    422,
                    'Unpublish the course before deleting it.'
                );

                $before = $this->auditSnapshot(
                    $lockedCourse
                );

                $lockedCourse->updated_by_admin_id =
                    $admin->id;

                $lockedCourse->save();

                $lockedCourse->delete();

                AuditLog::record(
                    actorType: 'ADMIN',

                    actorId: $admin->id,

                    action: 'COURSE_DELETED',

                    module: 'EDUCATION',

                    targetType: 'COURSE',

                    targetId: $lockedCourse->id,

                    oldValues: $before,

                    newValues: [
                        'deleted_at' => $lockedCourse
                            ->deleted_at
                            ?->toISOString(),
                    ],

                    ipAddress: $request->ip(),

                    userAgent: $request->userAgent()
                );
            }
        );

        return redirect()
            ->route(
                'admin.education.courses.index'
            )
            ->with(
                'success',
                'Course deleted successfully.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN-03 — Authenticated Admin Resolver
    |--------------------------------------------------------------------------
    |
    | This intentionally mirrors the frozen ADMIN-02E controller boundary.
    |
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
            'view' => $admin->hasPermission(
                'education.view'
            ),

            'create' => $admin->hasPermission(
                'education.create'
            ),

            'update' => $admin->hasPermission(
                'education.update'
            ),

            'delete' => $admin->hasPermission(
                'education.delete'
            ),

            'publish' => $admin->hasPermission(
                'education.publish'
            ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Audit Snapshot
    |--------------------------------------------------------------------------
    |
    | Long course descriptions are represented by SHA-256 digest + length so
    | audit records prove that content changed without duplicating potentially
    | very large lesson/course text into the audit table.
    |
    */

    private function auditSnapshot(
        Course $course
    ): array {
        $description =
            $course->description;

        return [
            'uuid' => $course->uuid,

            'code' => $course->code,

            'slug' => $course->slug,

            'title' => $course->title,

            'short_description' => $course->short_description,

            'description_sha256' => $description !== null
                    ? hash(
                        'sha256',
                        $description
                    )
                    : null,

            'description_length' => $description !== null
                    ? mb_strlen(
                        $description
                    )
                    : 0,

            'thumbnail_path' => $course->thumbnail_path,

            'language_code' => $course->language_code,

            'access_level' => $course->access_level,

            'status' => $course->status,

            'published_at' => $course
                ->published_at
                ?->toISOString(),

            'sort_order' => $course->sort_order,

            'is_featured' => $course->is_featured,

            'created_by_admin_id' => $course->created_by_admin_id,

            'updated_by_admin_id' => $course->updated_by_admin_id,
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

    /*
    |--------------------------------------------------------------------------
    | Course Thumbnail Storage
    |--------------------------------------------------------------------------
    */

    private function storeCourseThumbnail(
        UploadedFile $image,
        string $courseUuid
    ): string {
        $extension = strtolower(
            $image->extension()
        );
        $filename = 'thumbnail-'
            .Str::uuid()
            .'.'
            .$extension;
        $path = $image->storeAs(
            'education/courses/'.$courseUuid,
            $filename,
            'public'
        );

        if (! is_string($path) || trim($path) === '') {
            throw new RuntimeException(
                'Unable to store the course thumbnail image.'
            );
        }

        return $path;
    }

    private function deleteManagedCourseThumbnail(
        string $path,
        string $courseUuid
    ): void {
        $normalized = ltrim(
            str_replace('\\', '/', trim($path)),
            '/'
        );
        $prefix = 'education/courses/'
            .$courseUuid
            .'/';

        if (! str_starts_with($normalized, $prefix)) {
            return;
        }

        Storage::disk('public')->delete(
            $normalized
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Language Code Normalization
    |--------------------------------------------------------------------------
    */

    private function normalizeLanguageCode(
        string $languageCode
    ): string {
        $languageCode = trim(
            str_replace(
                '_',
                '-',
                $languageCode
            )
        );

        $parts = explode(
            '-',
            $languageCode
        );

        $language = strtolower(
            $parts[0]
            ?? ''
        );

        if (
            ! isset($parts[1])
            || $parts[1] === ''
        ) {
            return $language;
        }

        return $language
            .'-'
            .strtoupper(
                $parts[1]
            );
    }
}
