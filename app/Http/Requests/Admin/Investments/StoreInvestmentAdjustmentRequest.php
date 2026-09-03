<?php

namespace App\Http\Requests\Admin\Investments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvestmentAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'target_type' => ['required', Rule::in(['TRADE', 'COST'])],
            'target_id' => ['required', 'integer', 'min:1'],
            'adjustment_type' => ['required', Rule::in(['REVERSAL', 'ADJUSTMENT'])],
            'realized_pnl_delta_usdt' => ['nullable', 'numeric', 'decimal:0,8'],
            'approved_cost_delta_usdt' => ['nullable', 'numeric', 'decimal:0,8'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
