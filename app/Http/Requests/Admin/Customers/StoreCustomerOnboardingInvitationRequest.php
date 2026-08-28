<?php

namespace App\Http\Requests\Admin\Customers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerOnboardingInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin !== null
            && $admin->hasPermission(
                'users.invite'
            );
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

            'referral_code' => [
                'nullable',
                'string',
                'max:30',
            ],

            'internal_note' => [
                'nullable',
                'string',
                'max:500',
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

            'whatsapp_number' =>
                $whatsapp,

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

            'referral_code' => strtoupper(
                trim(
                    (string) $this->input(
                        'referral_code'
                    )
                )
            ) ?: null,

            'internal_note' => trim(
                (string) $this->input(
                    'internal_note'
                )
            ) ?: null,
        ]);
    }
}
