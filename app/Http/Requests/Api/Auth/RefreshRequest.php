<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RefreshRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'refresh_token' => [
                'required',
                'string',
                'min:64',
                'max:512',
            ],

            'installation_id' => [
                'required',
                'uuid',
            ],
            'quick_unlock_totp_code' => [
                'nullable',
                'digits:6',
            ],
        ];
    }
}
