<?php

namespace App\Http\Requests\Api\Profile;

use Illuminate\Foundation\Http\FormRequest;

class RequestWhatsAppChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_whatsapp_number' => [
                'required',
                'string',
                'regex:/^\+[1-9]\d{7,14}$/',
                'max:30',
            ],

            'totp_code' => [
                'required',
                'digits:6',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'new_whatsapp_number' =>
                preg_replace(
                    '/[\s\-\(\)]+/',
                    '',
                    (string) $this->input(
                        'new_whatsapp_number'
                    )
                ),
        ]);
    }
}
