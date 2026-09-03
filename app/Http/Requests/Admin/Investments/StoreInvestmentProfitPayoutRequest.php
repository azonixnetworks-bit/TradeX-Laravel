<?php

namespace App\Http\Requests\Admin\Investments;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvestmentProfitPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'txid' => ['required', 'string', 'size:64', 'regex:/^[A-Fa-f0-9]{64}$/'],
            'from_address' => ['nullable', 'string', 'max:191'],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
