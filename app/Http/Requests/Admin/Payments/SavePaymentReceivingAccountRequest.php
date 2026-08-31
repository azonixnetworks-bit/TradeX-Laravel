<?php

namespace App\Http\Requests\Admin\Payments;

use App\Models\AdminUser;
use App\Models\PaymentReceivingAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePaymentReceivingAccountRequest
    extends FormRequest
{
    public function authorize(): bool
    {
        $admin =
            auth('admin')->user();

        return $admin
            instanceof AdminUser
            && (
                $admin->isSuperAdmin()
                || $admin
                    ->hasPermission(
                        'payment_accounts.manage'
                    )
            );
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' =>
                strtoupper(
                    trim(
                        (string) $this
                            ->input(
                                'type'
                            )
                    )
                ),
            'country_code' =>
                strtoupper(
                    trim(
                        (string) $this
                            ->input(
                                'country_code'
                            )
                    )
                ),
            'currency' =>
                strtoupper(
                    trim(
                        (string) $this
                            ->input(
                                'currency'
                            )
                    )
                ),
            'coin_symbol' =>
                strtoupper(
                    trim(
                        (string) $this
                            ->input(
                                'coin_symbol'
                            )
                    )
                ),
            'network' =>
                strtoupper(
                    trim(
                        (string) $this
                            ->input(
                                'network'
                            )
                    )
                ),
        ]);

        foreach (
            [
                'is_active',
                'remove_qr',
            ]
            as $field
        ) {
            if (
                $this->has(
                    $field
                )
            ) {
                $this->merge([
                    $field =>
                        filter_var(
                            $this->input(
                                $field
                            ),
                            FILTER_VALIDATE_BOOL,
                            FILTER_NULL_ON_FAILURE
                        ),
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'type' => [
                'required',
                Rule::in([
                    PaymentReceivingAccount::TYPE_BANK,
                    PaymentReceivingAccount::TYPE_CRYPTO,
                ]),
            ],
            'display_name' => [
                'required',
                'string',
                'min:2',
                'max:120',
            ],
            'country_code' => [
                'required',
                'string',
                'regex:/^[A-Z]{2}$/',
            ],
            'currency' => [
                'required',
                'string',
                'regex:/^[A-Z0-9]{2,12}$/',
            ],
            'amount_scale' => [
                'required',
                'integer',
                'min:0',
                'max:18',
            ],
            'tender_amount' => [
                'required',
                'string',
                'max:80',
            ],
            'bank_name' => [
                'nullable',
                'required_if:type,'
                    .PaymentReceivingAccount::TYPE_BANK,
                'string',
                'max:120',
            ],
            'bank_branch' => [
                'nullable',
                'required_if:type,'
                    .PaymentReceivingAccount::TYPE_BANK,
                'string',
                'max:120',
            ],
            'account_holder' => [
                'nullable',
                'required_if:type,'
                    .PaymentReceivingAccount::TYPE_BANK,
                'string',
                'max:160',
            ],
            'account_number' => [
                'nullable',
                'required_if:type,'
                    .PaymentReceivingAccount::TYPE_BANK,
                'string',
                'min:3',
                'max:191',
            ],
            'coin_name' => [
                'nullable',
                'required_if:type,'
                    .PaymentReceivingAccount::TYPE_CRYPTO,
                'string',
                'max:80',
            ],
            'coin_symbol' => [
                'nullable',
                'required_if:type,'
                    .PaymentReceivingAccount::TYPE_CRYPTO,
                'string',
                'regex:/^[A-Z0-9._-]{2,20}$/',
            ],
            'network' => [
                'nullable',
                'required_if:type,'
                    .PaymentReceivingAccount::TYPE_CRYPTO,
                'string',
                'max:60',
            ],
            'wallet_address' => [
                'nullable',
                'required_if:type,'
                    .PaymentReceivingAccount::TYPE_CRYPTO,
                'string',
                'min:8',
                'max:255',
            ],
            'qr_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
            'remove_qr' => [
                'nullable',
                'boolean',
            ],
            'instructions' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'is_active' => [
                'required',
                'boolean',
            ],
            'display_order' => [
                'required',
                'integer',
                'min:0',
                'max:10000',
            ],
        ];
    }
}
