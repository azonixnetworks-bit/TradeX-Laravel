<?php

namespace App\Http\Requests\Admin\Investments;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvestmentTopUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,8'],
            'txid' => ['required', 'string', 'size:64', 'regex:/^[A-Fa-f0-9]{64}$/'],
            'sender_address' => ['nullable', 'string', 'max:191'],
            'receiving_address' => [
                'required',
                'string',
                'regex:/^T[1-9A-HJ-NP-Za-km-z]{33}$/',
            ],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
