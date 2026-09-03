<?php

namespace App\Http\Requests\Admin\Investments;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

class RecordInvestmentRiskAcceptanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission(
                'investments.risk_acceptance.record'
            );
    }

    public function rules(): array
    {
        return [
            'accepted_at' => [
                'required',
                'date',
                'before_or_equal:now',
            ],
            'acceptance_reference' => [
                'required',
                'string',
                'min:3',
                'max:191',
            ],
            'accepted_ip' => ['nullable', 'ip'],
            'accepted_user_agent' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'ack_read_disclosure' => ['accepted'],
            'ack_capital_loss' => ['accepted'],
            'ack_crypto_volatility' => ['accepted'],
            'ack_stablecoin_risk' => ['accepted'],
            'ack_network_cyber_risk' => ['accepted'],
            'ack_regulatory_risk' => ['accepted'],
            'ack_variable_returns' => ['accepted'],
            'ack_early_exit_rule' => ['accepted'],
        ];
    }
}
