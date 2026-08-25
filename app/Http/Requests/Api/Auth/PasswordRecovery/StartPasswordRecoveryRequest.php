<?php

namespace App\Http\Requests\Api\Auth\PasswordRecovery;

use Illuminate\Foundation\Http\FormRequest;

class StartPasswordRecoveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
            ],
            'whatsapp_number' => [
                'required',
                'string',
                'regex:/^\+[1-9]\d{7,14}$/',
                'max:30',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(
                trim(
                    (string) $this->input('email')
                )
            ),
            'whatsapp_number' => preg_replace(
                '/[\s\-\(\)]+/',
                '',
                (string) $this->input(
                    'whatsapp_number'
                )
            ),
        ]);
    }
}
