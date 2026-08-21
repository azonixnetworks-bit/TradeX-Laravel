<?php

namespace App\Http\Requests\Admin\Education;

use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCourseRequest extends FormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | Updating normal course metadata requires the exact frozen permission:
    | education.update
    |
    | Publishing is intentionally excluded from this request and will remain
    | behind the separate education.publish permission boundary.
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

        if (
            $this->has('language_code')
            && is_string($this->input('language_code'))
        ) {
            $normalized['language_code'] = $this->normalizeLanguageCode(
                (string) $this->input('language_code')
            );
        }

        if (
            $this->has('access_level')
            && is_string($this->input('access_level'))
        ) {
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
    | "sometimes" makes the request safe for PATCH-style updates while each
    | supplied field still receives its full validation contract.
    |
    | status and published_at are prohibited here so education.update cannot
    | be used to bypass the separate education.publish permission.
    |
    */

    public function rules(): array
    {
        $courseId = $this->courseId();

        return [
            'uuid' => [
                'prohibited',
            ],

            'code' => [
                'sometimes',
                'required',
                'string',
                'max:80',
                'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                Rule::unique('courses', 'code')
                    ->ignore($courseId),
            ],

            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:220',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('courses', 'slug')
                    ->ignore($courseId),
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

            'thumbnail_path' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',
            ],

            'language_code' => [
                'sometimes',
                'required',
                'string',
                'max:10',
                'regex:/^[a-z]{2,3}(?:-[A-Z]{2})?$/',
            ],

            'access_level' => [
                'sometimes',
                'required',
                Rule::in([
                    Course::ACCESS_FREE,
                    Course::ACCESS_PREMIUM,
                ]),
            ],

            'sort_order' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'max:4294967295',
            ],

            'is_featured' => [
                'sometimes',
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

            'uuid.prohibited' => 'The course UUID cannot be changed.',
            'status.prohibited' => 'Course publication status must be changed through the authorized publish workflow.',
            'published_at.prohibited' => 'The publish time is controlled by the authorized publish workflow.',
            'created_by_admin_id.prohibited' => 'The original creating admin cannot be changed through this request.',
            'updated_by_admin_id.prohibited' => 'The updating admin is determined from the authenticated admin session.',
            'deleted_at.prohibited' => 'The deletion state cannot be changed through the normal course update request.',
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Route Course Resolution
    |--------------------------------------------------------------------------
    |
    | The future Education route will use {course}. This helper supports both
    | implicit model binding and a numeric route value while keeping the unique
    | validation rules independent from controller implementation details.
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
