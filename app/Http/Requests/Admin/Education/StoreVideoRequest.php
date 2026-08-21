<?php

namespace App\Http\Requests\Admin\Education;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVideoRequest extends FormRequest
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
            'short_description',
            'storage_disk',
            'source_path',
            'hls_master_path',
            'streaming_asset_key',
            'thumbnail_path',
            'mime_type',
        ] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $normalized[$field] = trim((string) $this->input($field));
            }
        }

        foreach ([
            'source_path',
            'hls_master_path',
            'thumbnail_path',
        ] as $pathField) {
            if (array_key_exists($pathField, $normalized)) {
                $normalized[$pathField] = str_replace(
                    '\\',
                    '/',
                    $normalized[$pathField]
                );
            }
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
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],

            'storage_disk' => [
                'required',
                'string',
                Rule::in([
                    'private',
                    's3-private',
                ]),
            ],
            'source_path' => [
                'required',
                'string',
                'max:500',
                $this->protectedLogicalPathRule(),
            ],
            'hls_master_path' => [
                'nullable',
                'string',
                'max:500',
                $this->protectedLogicalPathRule(),
            ],
            'streaming_asset_key' => [
                'nullable',
                'string',
                'max:255',
                $this->internalAssetKeyRule(),
            ],
            'thumbnail_path' => [
                'nullable',
                'string',
                'max:500',
                $this->protectedLogicalPathRule(),
            ],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'file_size_bytes' => ['nullable', 'integer', 'min:0'],
            'mime_type' => ['nullable', 'string', 'max:120'],
            'is_downloadable' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:4294967295'],

            'processing_status' => ['prohibited'],
            'processing_error' => ['prohibited'],
            'processed_at' => ['prohibited'],
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
            'storage_disk.in' => 'Video storage must use an approved private disk: private or s3-private.',
            'course_id.prohibited' => 'The parent course is resolved from the protected route.',
            'course_module_id.prohibited' => 'The parent module is resolved from the protected route.',
            'lesson_id.prohibited' => 'The parent lesson is resolved from the protected route.',
            'processing_status.prohibited' => 'Video processing status is controlled by the media processing pipeline.',
            'processing_error.prohibited' => 'Video processing errors are controlled by the media processing pipeline.',
            'processed_at.prohibited' => 'Video processing time is controlled by the media processing pipeline.',
            'status.prohibited' => 'Video publication status cannot be set during creation.',
            'published_at.prohibited' => 'Video publication time is controlled by the authorized publish workflow.',
        ];
    }

    /**
     * Require a canonical, relative logical storage path.
     *
     * This blocks public/signed URLs, absolute filesystem paths,
     * Windows drive paths, path traversal, query strings/fragments
     * and control characters. The storage disk is supplied separately.
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


    /**
     * Streaming asset keys are provider identifiers, never URLs.
     */
    private function internalAssetKeyRule(): Closure
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

            $key = trim($value);

            if (
                preg_match('~^(?:https?:)?//~i', $key) === 1
                || preg_match('/[\x00-\x1F\x7F]/', $key) === 1
            ) {
                $fail(
                    'The '.$attribute.' must be an internal provider asset key, not a URL.'
                );
            }
        };
    }

}
