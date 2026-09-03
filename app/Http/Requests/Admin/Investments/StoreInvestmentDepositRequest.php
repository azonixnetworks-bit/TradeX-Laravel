<?php

namespace App\Http\Requests\Admin\Investments;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

class StoreInvestmentDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission(
                'investments.deposits.record'
            );
    }

    public function rules(): array
    {
        return [
            'amount' => [
                'required',
                'string',
                'regex:/^\d{1,20}(?:\.\d{1,8})?$/',
            ],
            'txid' => [
                'required',
                'string',
                'max:190',
                'regex:'.config(
                    'tradex_investments.deposit.txid_pattern'
                ),
            ],
            'sender_address' => [
                'nullable',
                'string',
                'max:100',
                'regex:'.config(
                    'tradex_investments.deposit.tron_address_pattern'
                ),
            ],
            'receiving_address' => [
                'required',
                'string',
                'max:100',
                'regex:'.config(
                    'tradex_investments.deposit.tron_address_pattern'
                ),
            ],
            'occurred_at' => [
                'required',
                'date',
                'before_or_equal:now',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
