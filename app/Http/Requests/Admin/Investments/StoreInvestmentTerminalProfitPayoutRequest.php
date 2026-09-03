<?php

namespace App\Http\Requests\Admin\Investments;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvestmentTerminalProfitPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'txid' => ['required', 'string', 'min:6', 'max:190'],
            'from_address' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
