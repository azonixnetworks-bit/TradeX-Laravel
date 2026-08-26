<?php

namespace App\Http\Requests\Admin\Notifications;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

class SendNotificationBroadcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission(
                'notifications.view'
            )
            && $admin->hasPermission(
                'notifications.send'
            );
    }

    protected function prepareForValidation(): void
    {
        $publishAt =
            $this->input(
                'publish_at'
            );

        $this->merge([
            'publish_at' =>
                is_scalar($publishAt)
                && trim(
                    (string) $publishAt
                ) !== ''
                    ? trim(
                        (string) $publishAt
                    )
                    : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'publish_at' => [
                'nullable',
                'date',
            ],
        ];
    }
}
