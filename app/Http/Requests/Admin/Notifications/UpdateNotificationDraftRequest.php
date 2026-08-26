<?php

namespace App\Http\Requests\Admin\Notifications;

use App\Models\AdminUser;
use App\Models\NotificationMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission(
                'notifications.view'
            )
            && $admin->hasPermission(
                'notifications.create'
            );
    }

    protected function prepareForValidation(): void
    {
        $resourceUuid =
            $this->input(
                'action_resource_uuid'
            );

        $expiresAt =
            $this->input(
                'expires_at'
            );

        $this->merge([
            'title' =>
                trim(
                    (string) $this->input(
                        'title'
                    )
                ),

            'body' =>
                trim(
                    (string) $this->input(
                        'body'
                    )
                ),

            'action_resource_uuid' =>
                is_scalar($resourceUuid)
                && trim(
                    (string) $resourceUuid
                ) !== ''
                    ? trim(
                        (string) $resourceUuid
                    )
                    : null,

            'expires_at' =>
                is_scalar($expiresAt)
                && trim(
                    (string) $expiresAt
                ) !== ''
                    ? trim(
                        (string) $expiresAt
                    )
                    : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'audience_access_level' => [
                'required',
                Rule::in([
                    NotificationMessage::AUDIENCE_ALL,
                    NotificationMessage::AUDIENCE_FREE,
                    NotificationMessage::AUDIENCE_PREMIUM,
                ]),
            ],

            'category' => [
                'required',
                Rule::in(
                    NotificationMessage::CATEGORIES
                ),
            ],

            'priority' => [
                'required',
                Rule::in(
                    NotificationMessage::PRIORITIES
                ),
            ],

            'title' => [
                'required',
                'string',
                'min:3',
                'max:180',
            ],

            'body' => [
                'required',
                'string',
                'min:3',
                'max:5000',
            ],

            'action_type' => [
                'required',
                Rule::in(
                    NotificationMessage::ACTION_TYPES
                ),
            ],

            'action_resource_uuid' => [
                'nullable',
                'uuid',
            ],

            'expires_at' => [
                'nullable',
                'date',
                'after:now',
            ],
        ];
    }
}
