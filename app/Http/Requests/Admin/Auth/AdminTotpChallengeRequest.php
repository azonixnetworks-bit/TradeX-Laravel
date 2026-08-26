<?php

namespace App\Http\Requests\Admin\Auth;

use Illuminate\Foundation\Http\FormRequest;

class AdminTotpChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' =>
                preg_replace(
                    '/\s+/',
                    '',
                    (string) $this->input(
                        'code'
                    )
                ),
        ]);
    }

    public function rules(): array
    {
        $digits =
            (int) config(
                'tradex_auth.totp.digits',
                6
            );

        return [
            'code' => [
                'required',
                'string',
                'regex:/^\d{'
                    .$digits
                    .'}$/',
            ],
        ];
    }
}
