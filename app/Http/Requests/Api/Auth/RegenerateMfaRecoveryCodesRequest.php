<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegenerateMfaRecoveryCodesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => [
                'required',
                'string',
                'max:1024',
            ],
            'totp_code' => [
                'required',
                'digits:6',
            ],
        ];
    }
}
