<?php

namespace App\Http\Requests\Api\Notifications;

use App\Models\NotificationMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (
            [
                'status',
                'category',
            ] as $field
        ) {
            if ($this->has($field)) {
                $this->merge([
                    $field => strtoupper(
                        trim(
                            (string) $this->input(
                                $field
                            )
                        )
                    ),
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'status' => [
                'sometimes',
                'string',
                Rule::in([
                    'READ',
                    'UNREAD',
                ]),
            ],

            'category' => [
                'sometimes',
                'string',
                Rule::in(
                    NotificationMessage::CATEGORIES
                ),
            ],

            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:'.max(
                    1,
                    (int) config(
                        'tradex_notifications_api.max_per_page',
                        50
                    )
                ),
            ],
        ];
    }
}
