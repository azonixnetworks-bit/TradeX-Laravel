<?php

namespace App\Http\Requests\Admin\Education;

use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreCourseRequest extends FormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | ADMIN-03 intentionally follows the existing ADMIN-02E request-level
    | authorization pattern. The generic AdminPermission middleware is not
    | relied upon here.
    |
    | Creating a course requires the exact frozen permission:
    | education.create
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

        if ($this->filled('code')) {
            $normalized['code'] = Str::upper(
                trim((string) $this->input('code'))
            );
        }

        if ($this->filled('slug')) {
            $normalized['slug'] = Str::lower(
                trim((string) $this->input('slug'))
            );
        }

        if ($this->has('title') && is_string($this->input('title'))) {
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

        if ($this->filled('language_code')) {
            $normalized['language_code'] = $this->normalizeLanguageCode(
                (string) $this->input('language_code')
            );
        }

        if ($this->filled('access_level')) {
            $normalized['access_level'] = Str::upper(
                trim((string) $this->input('access_level'))
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
    | Publication fields are deliberately prohibited during normal creation.
    | A course must first be created as DRAFT. Publishing will be handled by a
    | separate action protected by the frozen education.publish permission.
    |
    | Server-controlled identity/audit fields are also prohibited so they
    | cannot be spoofed by an admin form or future API client.
    |
    */

    public function rules(): array
    {
        return [
            'uuid' => [
                'prohibited',
            ],

            'code' => [
                'required',
                'string',
                'max:80',
                'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                Rule::unique('courses', 'code'),
            ],

            'slug' => [
                'required',
                'string',
                'max:220',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('courses', 'slug'),
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

            'thumbnail_path' => [
                'nullable',
                'string',
                'max:500',
            ],

            'language_code' => [
                'required',
                'string',
                'max:10',
                'regex:/^[a-z]{2,3}(?:-[A-Z]{2})?$/',
            ],

            'access_level' => [
                'required',
                Rule::in([
                    Course::ACCESS_FREE,
                    Course::ACCESS_PREMIUM,
                ]),
            ],

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
                'max:4294967295',
            ],

            'is_featured' => [
                'nullable',
                'boolean',
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
            'code.regex' => 'The course code may contain only uppercase letters, numbers, dots, underscores and hyphens.',
            'slug.regex' => 'The course slug may contain only lowercase letters, numbers and single hyphens between words.',
            'language_code.regex' => 'The language code must use a format such as ta, en, si, hi or en-US.',

            'uuid.prohibited' => 'The course UUID is generated by the server.',
            'status.prohibited' => 'A new course is created as DRAFT. Use the authorized publish workflow to publish it.',
            'published_at.prohibited' => 'The publish time is controlled by the authorized publish workflow.',
            'created_by_admin_id.prohibited' => 'The creating admin is determined from the authenticated admin session.',
            'updated_by_admin_id.prohibited' => 'The updating admin is determined by the server.',
            'deleted_at.prohibited' => 'The deletion state cannot be supplied when creating a course.',
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Language Code Normalization
    |--------------------------------------------------------------------------
    */

    private function normalizeLanguageCode(string $languageCode): string
    {
        $languageCode = trim(
            str_replace('_', '-', $languageCode)
        );

        $parts = explode(
            '-',
            $languageCode
        );

        $language = Str::lower(
            $parts[0] ?? ''
        );

        if (! isset($parts[1]) || $parts[1] === '') {
            return $language;
        }

        return $language.'-'.Str::upper(
            $parts[1]
        );
    }
}
