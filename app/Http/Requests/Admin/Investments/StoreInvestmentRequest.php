<?php

namespace App\Http\Requests\Admin\Investments;

use App\Models\AdminUser;
use App\Models\Investment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvestmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission('investments.create');
    }

    public function rules(): array
    {
        return [
            'customer_email' => [
                'required',
                'email:rfc',
                'max:190',
            ],
            'product_code' => [
                'required',
                'string',
                'max:40',
            ],
            'deposit_amount' => [
                'required',
                'numeric',
                'min:1',
                'max:99999999999999999999',
            ],
            'investment_period_years' => [
                'required',
                'integer',
                Rule::in([1, 2, 5]),
            ],
            'profit_review_interval_months' => [
                'required',
                'integer',
                Rule::in([2, 4, 6]),
            ],
            'settlement_mode' => [
                'required',
                Rule::in([
                    Investment::SETTLEMENT_ON_MATURITY,
                    Investment::SETTLEMENT_CUSTOM_DATE,
                ]),
            ],
            'custom_settlement_date' => [
                'nullable',
                'required_if:settlement_mode,'.Investment::SETTLEMENT_CUSTOM_DATE,
                'date',
                'after:today',
            ],
            'payout_wallet_address' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^T[1-9A-HJ-NP-Za-km-z]{33}$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'payout_wallet_address.regex' =>
                'The payout wallet must be a valid-looking TRON/TRC20 address.',
        ];
    }
}
