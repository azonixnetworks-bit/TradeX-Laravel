<?php

namespace App\Http\Requests\Admin\Education;

use App\Models\Assignment;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin !== null
            && $admin->hasPermission('education.create');
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach ([
            'title',
            'heading',
            'reference_image_path',
            'submission_instructions',
        ] as $field) {
            if (
                $this->has($field)
                && is_string($this->input($field))
            ) {
                $normalized[$field] = trim(
                    (string) $this->input($field)
                );
            }
        }

        if (
            $this->has('submission_method')
            && is_string($this->input('submission_method'))
        ) {
            $normalized['submission_method'] =
                Str::upper(
                    trim(
                        (string) $this->input(
                            'submission_method'
                        )
                    )
                );
        }

        if (
            array_key_exists(
                'reference_image_path',
                $normalized
            )
        ) {
            $normalized['reference_image_path'] =
                str_replace(
                    '\\',
                    '/',
                    $normalized['reference_image_path']
                );
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    public function rules(): array
    {
        return [
            'uuid' => ['prohibited'],
            'course_id' => ['prohibited'],
            'course_module_id' => ['prohibited'],
            'lesson_id' => ['prohibited'],

            'title' => [
                'required',
                'string',
                'max:200',
            ],

            'heading' => [
                'nullable',
                'string',
                'max:250',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'reference_image_path' => [
                'nullable',
                'string',
                'max:500',
                $this->protectedLogicalPathRule(),
            ],

            /*
            |--------------------------------------------------------------------------
            | ADMIN-ONLY Assignment Material Upload
            |--------------------------------------------------------------------------
            |
            | This is question/reference material supplied BY an admin.
            | It is not a student/learner answer upload.
            |
            */
            'material_file' => [
                'nullable',
                'file',
                'mimes:'.implode(
                    ',',
                    Assignment::MATERIAL_ALLOWED_EXTENSIONS
                ),
                'max:'.Assignment::MATERIAL_MAX_UPLOAD_KB,
            ],

            /*
            |--------------------------------------------------------------------------
            | Server-Controlled Material Metadata
            |--------------------------------------------------------------------------
            */
            'material_disk' => ['prohibited'],
            'material_path' => ['prohibited'],
            'material_original_name' => ['prohibited'],
            'material_mime_type' => ['prohibited'],
            'material_size_bytes' => ['prohibited'],
            'material_sha256' => ['prohibited'],
            'material_uploaded_at' => ['prohibited'],
            'remove_material' => ['prohibited'],

            'submission_method' => [
                'required',
                'string',
                Rule::in([
                    Assignment::SUBMISSION_WHATSAPP,
                    Assignment::SUBMISSION_NONE,
                ]),
            ],

            'submission_instructions' => [
                'nullable',
                'string',
            ],

            'is_required' => [
                'nullable',
                'boolean',
            ],

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
                'max:4294967295',
            ],

            /*
            |--------------------------------------------------------------------------
            | Learner Upload Fields Remain Forbidden
            |--------------------------------------------------------------------------
            */
            'submission_file' => ['prohibited'],
            'learner_submission' => ['prohibited'],
            'submission_upload' => ['prohibited'],
            'attachment' => ['prohibited'],

            'status' => ['prohibited'],
            'published_at' => ['prohibited'],
            'created_by_admin_id' => ['prohibited'],
            'updated_by_admin_id' => ['prohibited'],
            'deleted_at' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'material_file.file' =>
                'Choose a valid assignment material file.',

            'material_file.mimes' =>
                'Assignment material must be PDF, JPG, JPEG, PNG, WEBP, DOCX, XLSX, PPTX or TXT.',

            'material_file.max' =>
                'Assignment material must not exceed 25 MB.',

            'submission_method.in' =>
                'Assignment submission method must be WHATSAPP or NONE.',

            'lesson_id.prohibited' =>
                'The parent lesson is resolved from the protected route.',

            'material_path.prohibited' =>
                'Assignment material storage path is controlled by TradeX.',

            'material_sha256.prohibited' =>
                'Assignment material integrity metadata is controlled by TradeX.',

            'submission_file.prohibited' =>
                'Learner assignment files are not uploaded or stored through TradeX.',

            'learner_submission.prohibited' =>
                'Learner assignment files are not uploaded or stored through TradeX.',

            'submission_upload.prohibited' =>
                'Learner assignment files are not uploaded or stored through TradeX.',

            'attachment.prohibited' =>
                'Learner assignment files are not uploaded or stored through TradeX.',

            'status.prohibited' =>
                'Assignment publication status cannot be set during creation.',

            'published_at.prohibited' =>
                'Assignment publication time is controlled by the authorized publish workflow.',
        ];
    }

    private function protectedLogicalPathRule(): Closure
    {
        return static function (
            string $attribute,
            mixed $value,
            Closure $fail
        ): void {
            if (
                $value === null
                || $value === ''
            ) {
                return;
            }

            if (! is_string($value)) {
                return;
            }

            $path = trim($value);

            $normalized = str_replace(
                '\\',
                '/',
                $path
            );

            $hasScheme =
                preg_match(
                    '/^[A-Za-z][A-Za-z0-9+.-]*:/',
                    $normalized
                ) === 1;

            $hasTraversal =
                preg_match(
                    '~(?:^|/)\.\.(?:/|$)~',
                    $normalized
                ) === 1;

            $hasDotSegment =
                preg_match(
                    '~(?:^|/)\.(?:/|$)~',
                    $normalized
                ) === 1;

            $hasControlCharacter =
                preg_match(
                    '/[\x00-\x1F\x7F]/',
                    $path
                ) === 1;

            if (
                $path === ''
                || str_starts_with(
                    $normalized,
                    '/'
                )
                || $hasScheme
                || $hasTraversal
                || $hasDotSegment
                || str_contains(
                    $normalized,
                    '?'
                )
                || str_contains(
                    $normalized,
                    '#'
                )
                || $hasControlCharacter
            ) {
                $fail(
                    'The '.$attribute.' must be a protected relative logical path without URLs, traversal, query strings or fragments.'
                );
            }
        };
    }
}
