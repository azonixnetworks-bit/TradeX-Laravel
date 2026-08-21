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
            if ($this->has($field) && is_string($this->input($field))) {
                $normalized[$field] = trim((string) $this->input($field));
            }
        }

        if ($this->has('submission_method') && is_string($this->input('submission_method'))) {
            $normalized['submission_method'] = Str::upper(
                trim((string) $this->input('submission_method'))
            );
        }

        if (array_key_exists('reference_image_path', $normalized)) {
            $normalized['reference_image_path'] = str_replace(
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

            'title' => ['required', 'string', 'max:200'],
            'heading' => ['nullable', 'string', 'max:250'],
            'description' => ['nullable', 'string'],
            'reference_image_path' => [
                'nullable',
                'string',
                'max:500',
                $this->protectedLogicalPathRule(),
            ],
            'submission_method' => [
                'required',
                'string',
                Rule::in([
                    Assignment::SUBMISSION_WHATSAPP,
                    Assignment::SUBMISSION_NONE,
                ]),
            ],
            'submission_instructions' => ['nullable', 'string'],
            'is_required' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:4294967295'],

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
            'submission_method.in' => 'Assignment submission method must be WHATSAPP or NONE.',
            'lesson_id.prohibited' => 'The parent lesson is resolved from the protected route.',
            'submission_file.prohibited' => 'Learner assignment files are not uploaded or stored through TradeX.',
            'learner_submission.prohibited' => 'Learner assignment files are not uploaded or stored through TradeX.',
            'submission_upload.prohibited' => 'Learner assignment files are not uploaded or stored through TradeX.',
            'attachment.prohibited' => 'Learner assignment files are not uploaded or stored through TradeX.',
            'status.prohibited' => 'Assignment publication status cannot be set during creation.',
            'published_at.prohibited' => 'Assignment publication time is controlled by the authorized publish workflow.',
        ];
    }

    /**
     * Assignment reference media must remain a relative private logical path.
     * Learner submission files are never accepted by this request.
     */
    private function protectedLogicalPathRule(): Closure
    {
        return static function (
            string $attribute,
            mixed $value,
            Closure $fail
        ): void {
            if ($value === null || $value === '') {
                return;
            }

            if (! is_string($value)) {
                return;
            }

            $path = trim($value);
            $normalized = str_replace('\\', '/', $path);

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
                || str_starts_with($normalized, '/')
                || $hasScheme
                || $hasTraversal
                || $hasDotSegment
                || str_contains($normalized, '?')
                || str_contains($normalized, '#')
                || $hasControlCharacter
            ) {
                $fail(
                    'The '.$attribute.' must be a protected relative logical path without URLs, traversal, query strings or fragments.'
                );
            }
        };
    }

}
