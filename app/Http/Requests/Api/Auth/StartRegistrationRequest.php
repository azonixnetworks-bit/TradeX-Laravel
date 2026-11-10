<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StartRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:120',
            ],

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

            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols(),
            ],

            'referral_code' => [
                'nullable',
                'string',
                'max:30',
            ],

            'country_code' => [
                'required',
                'string',
                'size:2',
                'alpha',
            ],

            'language_code' => [
                'required',
                'string',
                Rule::in(
                    config(
                        'tradex_registration.allowed_languages',
                        ['en']
                    )
                ),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $whatsapp = preg_replace(
            '/[\s\-\(\)]+/',
            '',
            (string) $this->input(
                'whatsapp_number'
            )
        );

        $this->merge([
            'name' => trim(
                (string) $this->input('name')
            ),

            'email' => mb_strtolower(
                trim(
                    (string) $this->input(
                        'email'
                    )
                )
            ),

            'whatsapp_number' => $whatsapp,

            'referral_code' => strtoupper(
                trim(
                    (string) $this->input(
                        'referral_code'
                    )
                )
            ) ?: null,

            'country_code' => strtoupper(
                trim(
                    (string) $this->input(
                        'country_code'
                    )
                )
            ),

            'language_code' => strtolower(
                trim(
                    (string) $this->input(
                        'language_code'
                    )
                )
            ),
        ]);
    }
}
