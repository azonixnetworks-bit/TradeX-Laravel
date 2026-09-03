<?php

namespace App\Http\Requests\Admin\Investments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvestmentTradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['BUY', 'SELL'])],
            'asset_name' => ['required', 'string', 'max:120'],
            'asset_symbol' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Za-z0-9._-]+$/',
            ],
            'network' => ['nullable', 'string', 'max:60'],
            'contract_address' => ['nullable', 'string', 'max:191'],
            'exchange_broker' => ['required', 'string', 'max:120'],
            'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,18'],
            'executed_price_usdt' => ['required', 'numeric', 'gte:0', 'decimal:0,8'],
            'gross_value_usdt' => ['required', 'numeric', 'gt:0', 'decimal:0,8'],
            'fee_usdt' => ['nullable', 'numeric', 'gte:0', 'decimal:0,8'],
            'realized_pnl_usdt' => ['nullable', 'numeric', 'decimal:0,8'],
            'order_reference' => ['nullable', 'string', 'max:191'],
            'executed_at' => ['required', 'date', 'before_or_equal:now'],
        ];
    }
}
