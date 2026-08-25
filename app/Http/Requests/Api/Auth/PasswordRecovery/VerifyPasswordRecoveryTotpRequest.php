<?php

namespace App\Http\Requests\Api\Auth\PasswordRecovery;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPasswordRecoveryTotpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recovery_id' => [
                'required',
                'uuid',
            ],
            'recovery_token' => [
                'required',
                'string',
                'min:32',
                'max:200',
            ],
            'totp_code' => [
                'required',
                'digits:6',
            ],
        ];
    }
}
