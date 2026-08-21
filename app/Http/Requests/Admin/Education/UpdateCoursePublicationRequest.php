<?php

namespace App\Http\Requests\Admin\Education;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCoursePublicationRequest extends FormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Publication Action Constants
    |--------------------------------------------------------------------------
    */

    public const ACTION_PUBLISH = 'PUBLISH';

    public const ACTION_UNPUBLISH = 'UNPUBLISH';


    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | Course publication is a privileged operation and requires the exact
    | frozen permission:
    |
    | education.publish
    |
    | Normal education.create / education.update permissions are not enough.
    |
    */

    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin !== null
            && $admin->hasPermission('education.publish');
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
            $this->has('action')
            && is_string($this->input('action'))
        ) {
            $normalized['action'] = Str::upper(
                trim((string) $this->input('action'))
            );
        }

        if (
            $this->has('reason')
            && is_string($this->input('reason'))
        ) {
            $normalized['reason'] = trim(
                (string) $this->input('reason')
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
    | Only the requested publication action and an optional audit reason may
    | come from the client.
    |
    | status and published_at are deliberately prohibited. The controller /
    | service layer will derive those values server-side after authorization
    | and state checks:
    |
    | PUBLISH   -> status = PUBLISHED, published_at = server time
    | UNPUBLISH -> status = DRAFT,     published_at = null
    |
    */

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

            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'status' => [
                'prohibited',
            ],

            'published_at' => [
                'prohibited',
            ],

            'uuid' => [
                'prohibited',
            ],

            'course_id' => [
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
            'action.required' => 'Choose whether to publish or unpublish the course.',
            'action.in' => 'The publication action must be PUBLISH or UNPUBLISH.',

            'status.prohibited' => 'Course status is derived by the server from the authorized publication action.',
            'published_at.prohibited' => 'The publication time is controlled by the server.',
            'uuid.prohibited' => 'The course UUID cannot be changed through the publication action.',
            'course_id.prohibited' => 'The target course is resolved from the protected route.',
            'created_by_admin_id.prohibited' => 'The creating admin cannot be changed through the publication action.',
            'updated_by_admin_id.prohibited' => 'The updating admin is determined from the authenticated admin session.',
            'deleted_at.prohibited' => 'Deletion state cannot be changed through the publication action.',
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Convenience Helpers
    |--------------------------------------------------------------------------
    */

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

        return $reason === ''
            ? null
            : $reason;
    }
}