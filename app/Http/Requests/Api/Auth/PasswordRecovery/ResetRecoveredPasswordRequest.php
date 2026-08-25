<?php

namespace App\Http\Requests\Api\Auth\PasswordRecovery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetRecoveredPasswordRequest extends FormRequest
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
            'reset_token' => [
                'required',
                'string',
                'min:32',
                'max:255',
            ],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols(),
            ],
        ];
    }
}
