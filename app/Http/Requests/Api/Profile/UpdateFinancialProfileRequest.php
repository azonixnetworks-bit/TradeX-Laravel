<?php

namespace App\Http\Requests\Api\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFinancialProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'totp_code' => [
                'required',
                'digits:6',
            ],

            'paypal_email' => [
                'sometimes',
                'nullable',
                'email:rfc',
                'max:255',
            ],

            'binance_email' => [
                'sometimes',
                'nullable',
                'email:rfc',
                'max:255',
            ],

            'usdt_trc20_address' => [
                'sometimes',
                'nullable',
                'string',
                'regex:/^T[1-9A-HJ-NP-Za-km-z]{33}$/',
            ],

            'bank_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:120',
                'required_with:bank_branch,bank_account_holder,bank_account_number',
            ],

            'bank_branch' => [
                'sometimes',
                'nullable',
                'string',
                'max:120',
                'required_with:bank_name,bank_account_holder,bank_account_number',
            ],

            'bank_account_holder' => [
                'sometimes',
                'nullable',
                'string',
                'max:150',
                'required_with:bank_name,bank_branch,bank_account_number',
            ],

            'bank_account_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9\-\/ ]+$/',
                'required_with:bank_name,bank_branch,bank_account_holder',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        foreach (
            [
                'paypal_email',
                'binance_email',
            ] as $field
        ) {
            if ($this->exists($field)) {
                $value = trim(
                    (string) $this->input(
                        $field
                    )
                );

                $payload[$field] =
                    $value === ''
                        ? null
                        : mb_strtolower(
                            $value
                        );
            }
        }

        foreach (
            [
                'usdt_trc20_address',
                'bank_name',
                'bank_branch',
                'bank_account_holder',
                'bank_account_number',
            ] as $field
        ) {
            if ($this->exists($field)) {
                $value = trim(
                    (string) $this->input(
                        $field
                    )
                );

                $payload[$field] =
                    $value === ''
                        ? null
                        : $value;
            }
        }

        if ($payload !== []) {
            $this->merge(
                $payload
            );
        }
    }
}
