<?php

namespace App\Http\Requests\Admin\Education;

use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreModuleRequest extends FormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | Creating a course module is Education content creation and therefore
    | requires the exact frozen permission:
    |
    | education.create
    |
    | Publication remains a separate education.publish boundary.
    |
    */

    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin !== null
            && $admin->hasPermission('education.create');
    }


    /*
    |--------------------------------------------------------------------------
    | Input Normalization
    |--------------------------------------------------------------------------
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
    | Module code and slug are unique inside the parent course, matching the
    | ADMIN-03 course_modules database contract.
    |
    | Parent ownership is route-controlled through {course}; course_id is not
    | accepted from client input.
    |
    | New modules are always created as DRAFT. status and published_at are
    | server-controlled and cannot be supplied through this request.
    |
    */

    public function rules(): array
    {
        $courseId = $this->courseId();

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

        return [
            'uuid' => [
                'prohibited',
            ],

            'course_id' => [
                'prohibited',
            ],

            'code' => [
                'required',
                'string',
                'max:80',
                'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                $uniqueCode,
            ],

            'slug' => [
                'required',
                'string',
                'max:220',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $uniqueSlug,
            ],

            'title' => [
                'required',
                'string',
                'max:200',
            ],

            'short_description' => [
                'nullable',
                'string',
                'max:500',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'sort_order' => [
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
                'The parent course is resolved from the protected route and cannot be supplied by the client.',

            'code.regex' =>
                'The module code may contain only uppercase letters, numbers, dots, underscores and hyphens.',

            'code.unique' =>
                'This module code is already used inside the selected course.',

            'slug.regex' =>
                'The module slug may contain only lowercase letters, numbers and single hyphens between words.',

            'slug.unique' =>
                'This module slug is already used inside the selected course.',

            'uuid.prohibited' =>
                'The module UUID is generated by the server.',

            'status.prohibited' =>
                'New modules are created as Draft. Publication status must be changed through the authorized publish workflow.',

            'published_at.prohibited' =>
                'The module publication time is controlled by the authorized publish workflow.',

            'created_by_admin_id.prohibited' =>
                'The creating admin is determined from the authenticated admin session.',

            'updated_by_admin_id.prohibited' =>
                'The updating admin is determined from the authenticated admin session.',

            'deleted_at.prohibited' =>
                'Deletion state cannot be controlled through the module creation request.',
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Parent Course Resolver
    |--------------------------------------------------------------------------
    |
    | Future nested Education routes will use {course}. This helper supports
    | both implicit model binding and numeric route values.
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
}