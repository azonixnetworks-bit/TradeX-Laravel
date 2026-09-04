<?php

namespace App\Http\Requests\Admin\Investments;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvestmentSettlementWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'payout_network' => ['required', 'string', 'min:2', 'max:60'],
            'destination_wallet' => ['required', 'string', 'min:8', 'max:255'],
            'wallet_reference' => ['required', 'string', 'min:3', 'max:191'],
        ];
    }
}
