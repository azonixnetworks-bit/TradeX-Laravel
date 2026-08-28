<?php

namespace App\Http\Requests\Admin\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin !== null
            && $admin->hasPermission('users.view');
    }

    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],

            'segment' => [
                'nullable',
                Rule::in([
                    'PENDING',
                    'FREE',
                    'PREMIUM',
                    'RESTRICTED',
                    'RECENT',
                ]),
            ],

            'account_type' => [
                'nullable',
                Rule::in([
                    'FREE',
                    'PREMIUM',
                ]),
            ],

            'status' => [
                'nullable',
                Rule::in([
                    'PENDING',
                    'ACTIVE',
                    'SUSPENDED',
                    'BLOCKED',
                ]),
            ],

            'country' => [
                'nullable',
                'string',
                'size:2',
            ],

            'sort' => [
                'nullable',
                Rule::in([
                    'newest',
                    'oldest',
                    'name_asc',
                    'name_desc',
                ]),
            ],

            'per_page' => [
                'nullable',
                'integer',
                Rule::in([
                    10,
                    20,
                    50,
                    100,
                ]),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'segment' => strtoupper(
                trim(
                    (string) $this->query(
                        'segment',
                        ''
                    )
                )
            ) ?: null,

            'country' => strtoupper(
                trim(
                    (string) $this->query(
                        'country',
                        ''
                    )
                )
            ) ?: null,
        ]);
    }
}
