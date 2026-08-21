<?php

namespace App\Http\Requests\Admin\Education;

use App\Models\CourseModule;
use App\Models\Lesson;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateLessonRequest extends FormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | Updating lesson metadata requires the exact frozen permission:
    |
    | education.update
    |
    | Publication state remains isolated behind education.publish.
    |
    */

    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin !== null
            && $admin->hasPermission('education.update');
    }


    /*
    |--------------------------------------------------------------------------
    | Input Normalization
    |--------------------------------------------------------------------------
    |
    | Only normalize fields actually submitted so PATCH requests remain
    | partial-update safe.
    |
    */

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if (
            $this->has('code')
            && is_string($this->input('code'))
        ) {
            $normalized['code'] = Str::upper(
                trim((string) $this->input('code'))
            );
        }

        if (
            $this->has('slug')
            && is_string($this->input('slug'))
        ) {
            $normalized['slug'] = Str::lower(
                trim((string) $this->input('slug'))
            );
        }

        if (
            $this->has('title')
            && is_string($this->input('title'))
        ) {
            $normalized['title'] = trim(
                (string) $this->input('title')
            );
        }

        if (
            $this->has('heading')
            && is_string($this->input('heading'))
        ) {
            $normalized['heading'] = trim(
                (string) $this->input('heading')
            );
        }

        if (
            $this->has('short_description')
            && is_string($this->input('short_description'))
        ) {
            $normalized['short_description'] = trim(
                (string) $this->input('short_description')
            );
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | code and slug remain unique inside the parent course module while
    | ignoring the current lesson being edited.
    |
    | Parent ownership is route-controlled through {course} and {module}.
    | The client cannot move a lesson between courses or modules by submitting
    | course_id or course_module_id.
    |
    | status and published_at are deliberately prohibited so education.update
    | cannot bypass the separate education.publish security boundary.
    |
    */

    public function rules(): array
    {
        $moduleId = $this->moduleId();
        $lessonId = $this->lessonId();

        $uniqueCode = Rule::unique(
            'lessons',
            'code'
        );

        $uniqueSlug = Rule::unique(
            'lessons',
            'slug'
        );

        if ($moduleId !== null) {
            $uniqueCode->where(
                fn ($query) =>
                    $query->where(
                        'course_module_id',
                        $moduleId
                    )
            );

            $uniqueSlug->where(
                fn ($query) =>
                    $query->where(
                        'course_module_id',
                        $moduleId
                    )
            );
        }

        if ($lessonId !== null) {
            $uniqueCode->ignore(
                $lessonId
            );

            $uniqueSlug->ignore(
                $lessonId
            );
        }

        return [
            'uuid' => [
                'prohibited',
            ],

            'course_id' => [
                'prohibited',
            ],

            'course_module_id' => [
                'prohibited',
            ],

            'code' => [
                'sometimes',
                'required',
                'string',
                'max:80',
                'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                $uniqueCode,
            ],

            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:220',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $uniqueSlug,
            ],

            'title' => [
                'sometimes',
                'required',
                'string',
                'max:200',
            ],

            'heading' => [
                'sometimes',
                'nullable',
                'string',
                'max:250',
            ],

            'short_description' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'is_preview' => [
                'sometimes',
                'nullable',
                'boolean',
            ],

            'sort_order' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'max:4294967295',
            ],

            'status' => [
                'prohibited',
            ],

            'published_at' => [
                'prohibited',
            ],

            'created_by_admin_id' => [
                'prohibited',
            ],

            'updated_by_admin_id' => [
                'prohibited',
            ],

            'deleted_at' => [
                'prohibited',
            ],
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Validation Messages
    |--------------------------------------------------------------------------
    */

    public function messages(): array
    {
        return [
            'course_id.prohibited' =>
                'A lesson cannot be moved to another course through the update request. The parent course is resolved from the protected route.',

            'course_module_id.prohibited' =>
                'A lesson cannot be moved to another module through the update request. The parent module is resolved from the protected route.',

            'code.regex' =>
                'The lesson code may contain only uppercase letters, numbers, dots, underscores and hyphens.',

            'code.unique' =>
                'This lesson code is already used inside the selected module.',

            'slug.regex' =>
                'The lesson slug may contain only lowercase letters, numbers and single hyphens between words.',

            'slug.unique' =>
                'This lesson slug is already used inside the selected module.',

            'uuid.prohibited' =>
                'The lesson UUID cannot be changed.',

            'status.prohibited' =>
                'Lesson publication status cannot be changed through the normal update request.',

            'published_at.prohibited' =>
                'The lesson publication time is controlled by the authorized publish workflow.',

            'created_by_admin_id.prohibited' =>
                'The creating admin cannot be changed through the lesson update request.',

            'updated_by_admin_id.prohibited' =>
                'The updating admin is determined from the authenticated admin session.',

            'deleted_at.prohibited' =>
                'Deletion state cannot be controlled through the lesson update request.',
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Parent Module Resolver
    |--------------------------------------------------------------------------
    |
    | Nested Lesson routes will use {module}. This helper supports both implicit
    | model binding and numeric route values. The resolved ID is used only for
    | same-module uniqueness validation.
    |
    */

    private function moduleId(): ?int
    {
        $module = $this->route('module');

        if ($module instanceof CourseModule) {
            return (int) $module->getKey();
        }

        if (is_numeric($module)) {
            return (int) $module;
        }

        return null;
    }


    /*
    |--------------------------------------------------------------------------
    | Current Lesson Resolver
    |--------------------------------------------------------------------------
    |
    | Nested Lesson member routes will use {lesson}. The current lesson ID is
    | excluded from same-module code and slug uniqueness checks.
    |
    */

    private function lessonId(): ?int
    {
        $lesson = $this->route('lesson');

        if ($lesson instanceof Lesson) {
            return (int) $lesson->getKey();
        }

        if (is_numeric($lesson)) {
            return (int) $lesson;
        }

        return null;
    }
}