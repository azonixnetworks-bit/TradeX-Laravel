<?php

namespace App\Http\Requests\Api\Profile;

use Illuminate\Foundation\Http\FormRequest;

class RequestTelegramChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_telegram_username' => [
                'required',
                'string',
                'min:5',
                'max:32',
                'regex:/^[A-Za-z0-9_]+$/',
            ],

            'totp_code' => [
                'required',
                'digits:6',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $value = ltrim(
            trim(
                (string) $this->input(
                    'new_telegram_username'
                )
            ),
            '@'
        );

        $this->merge([
            'new_telegram_username' =>
                strtolower(
                    $value
                ),
        ]);
    }
}
