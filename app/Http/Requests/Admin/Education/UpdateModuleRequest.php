<?php

namespace App\Http\Requests\Admin\Education;

use App\Models\Course;
use App\Models\CourseModule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateModuleRequest extends FormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | Updating module metadata requires the exact frozen permission:
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
    | code and slug remain unique inside the parent course, while ignoring the
    | current module being edited.
    |
    | Parent course ownership is route-controlled. The client cannot move a
    | module between courses by submitting course_id.
    |
    | status and published_at are deliberately prohibited so education.update
    | cannot bypass education.publish.
    |
    */

    public function rules(): array
    {
        $courseId = $this->courseId();
        $moduleId = $this->moduleId();

        $uniqueCode = Rule::unique(
            'course_modules',
            'code'
        );

        $uniqueSlug = Rule::unique(
            'course_modules',
            'slug'
        );

        if ($courseId !== null) {
            $uniqueCode->where(
                fn ($query) =>
                    $query->where(
                        'course_id',
                        $courseId
                    )
            );

            $uniqueSlug->where(
                fn ($query) =>
                    $query->where(
                        'course_id',
                        $courseId
                    )
            );
        }

        if ($moduleId !== null) {
            $uniqueCode->ignore(
                $moduleId
            );

            $uniqueSlug->ignore(
                $moduleId
            );
        }

        return [
            'uuid' => [
                'prohibited',
            ],

            'course_id' => [
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
                'A module cannot be moved to another course through the update request. The parent course is resolved from the protected route.',

            'code.regex' =>
                'The module code may contain only uppercase letters, numbers, dots, underscores and hyphens.',

            'code.unique' =>
                'This module code is already used inside the selected course.',

            'slug.regex' =>
                'The module slug may contain only lowercase letters, numbers and single hyphens between words.',

            'slug.unique' =>
                'This module slug is already used inside the selected course.',

            'uuid.prohibited' =>
                'The module UUID cannot be changed.',

            'status.prohibited' =>
                'Module publication status cannot be changed through the normal update request.',

            'published_at.prohibited' =>
                'The module publication time is controlled by the authorized publish workflow.',

            'created_by_admin_id.prohibited' =>
                'The creating admin cannot be changed through the module update request.',

            'updated_by_admin_id.prohibited' =>
                'The updating admin is determined from the authenticated admin session.',

            'deleted_at.prohibited' =>
                'Deletion state cannot be controlled through the module update request.',
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Parent Course Resolver
    |--------------------------------------------------------------------------
    |
    | Supports future nested routes using:
    |
    | {course}
    |
    | with either implicit model binding or a numeric route value.
    |
    */

    private function courseId(): ?int
    {
        $course = $this->route('course');

        if ($course instanceof Course) {
            return (int) $course->getKey();
        }

        if (is_numeric($course)) {
            return (int) $course;
        }

        return null;
    }


    /*
    |--------------------------------------------------------------------------
    | Current Module Resolver
    |--------------------------------------------------------------------------
    |
    | Future nested module routes will use:
    |
    | {module}
    |
    | The ID is used only to exclude the current record from same-course
    | uniqueness validation.
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
}