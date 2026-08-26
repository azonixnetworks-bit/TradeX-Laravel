<?php

namespace App\Http\Requests\Admin\Auth;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

class AdminTotpConfirmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->user()
            instanceof AdminUser;
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
            'current_password' => [
                'required',
                'string',
                'current_password:admin',
            ],

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
