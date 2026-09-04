<?php

namespace App\Http\Requests\Admin\Investments;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvestmentSettlementAssetPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'network_fee_asset_symbol' => ['nullable', 'string', 'max:30'],
            'network_fee_units' => ['required', 'numeric', 'min:0', 'decimal:0,18'],
            'deduct_fee_from_customer' => ['nullable', 'boolean'],
            'txid' => ['required', 'string', 'min:6', 'max:190'],
            'from_address' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
