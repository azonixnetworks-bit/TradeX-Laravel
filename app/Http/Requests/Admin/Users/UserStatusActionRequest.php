<?php

namespace App\Http\Requests\Admin\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserStatusActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        if (! $admin) {
            return false;
        }

        return match ($this->input('action')) {

            'ACTIVATE' =>
                $admin->hasPermission('users.activate'),

            'SUSPEND' =>
                $admin->hasPermission('users.suspend'),

            'BLOCK' =>
                $admin->hasPermission('users.block'),

            default => false,
        };
    }

    public function rules(): array
    {
        return [
            'action' => [
                'required',
                Rule::in([
                    'ACTIVATE',
                    'SUSPEND',
                    'BLOCK',
                ]),
            ],

            'reason' => [
                'required',
                'string',
                'min:10',
                'max:500',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' =>
                'A reason is required for this account action.',

            'reason.min' =>
                'Please provide a meaningful reason of at least 10 characters.',

            'reason.max' =>
                'The reason may not exceed 500 characters.',
        ];
    }
}