<?php

namespace App\Http\Requests\Admin\Education;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateVideoPublicationRequest extends FormRequest
{
    public const ACTION_PUBLISH = 'PUBLISH';
    public const ACTION_UNPUBLISH = 'UNPUBLISH';

    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin !== null
            && $admin->hasPermission('education.publish');
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('action') && is_string($this->input('action'))) {
            $normalized['action'] = Str::upper(trim((string) $this->input('action')));
        }

        if ($this->has('reason') && is_string($this->input('reason'))) {
            $normalized['reason'] = trim((string) $this->input('reason'));
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    public function rules(): array
    {
        return [
            'action' => [
                'required',
                'string',
                Rule::in([
                    self::ACTION_PUBLISH,
                    self::ACTION_UNPUBLISH,
                ]),
            ],
            'reason' => ['nullable', 'string', 'max:1000'],
            'status' => ['prohibited'],
            'published_at' => ['prohibited'],
            'uuid' => ['prohibited'],
            'course_id' => ['prohibited'],
            'course_module_id' => ['prohibited'],
            'lesson_id' => ['prohibited'],
            'video_id' => ['prohibited'],
            'processing_status' => ['prohibited'],
            'processing_error' => ['prohibited'],
            'processed_at' => ['prohibited'],
            'created_by_admin_id' => ['prohibited'],
            'updated_by_admin_id' => ['prohibited'],
            'deleted_at' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Choose whether to publish or unpublish the video.',
            'action.in' => 'The video publication action must be PUBLISH or UNPUBLISH.',
            'status.prohibited' => 'Video status is derived by the server from the authorized publication action.',
            'published_at.prohibited' => 'The video publication time is controlled by the server.',
            'uuid.prohibited' => 'The uuid cannot be controlled through the video publication action.',
            'course_id.prohibited' => 'The course id cannot be controlled through the video publication action.',
            'course_module_id.prohibited' => 'The course module id cannot be controlled through the video publication action.',
            'lesson_id.prohibited' => 'The lesson id cannot be controlled through the video publication action.',
            'video_id.prohibited' => 'The video id cannot be controlled through the video publication action.',
            'processing_status.prohibited' => 'The processing status cannot be controlled through the video publication action.',
            'processing_error.prohibited' => 'The processing error cannot be controlled through the video publication action.',
            'processed_at.prohibited' => 'The processed at cannot be controlled through the video publication action.',
            'created_by_admin_id.prohibited' => 'The creating admin cannot be changed through the publication action.',
            'updated_by_admin_id.prohibited' => 'The updating admin is determined from the authenticated admin session.',
            'deleted_at.prohibited' => 'Deletion state cannot be changed through the publication action.',
        ];
    }

    public function publicationAction(): string
    {
        return (string) $this->validated('action');
    }

    public function isPublishAction(): bool
    {
        return $this->publicationAction() === self::ACTION_PUBLISH;
    }

    public function isUnpublishAction(): bool
    {
        return $this->publicationAction() === self::ACTION_UNPUBLISH;
    }

    public function auditReason(): ?string
    {
        $reason = $this->validated('reason');

        if (! is_string($reason)) {
            return null;
        }

        $reason = trim($reason);

        return $reason === '' ? null : $reason;
    }
}
