<?php

namespace App\Http\Requests\Admin\Investments;

use App\Models\InvestmentApprovedCost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvestmentApprovedCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'cost_type' => [
                'required',
                Rule::in(InvestmentApprovedCost::allowedTypes()),
            ],
            'amount_usdt' => ['required', 'numeric', 'gt:0', 'decimal:0,8'],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'source_reference' => ['required', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
