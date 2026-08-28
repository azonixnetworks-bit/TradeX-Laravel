<?php

namespace App\Http\Requests\Api\CopyTrading;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreCopyTradeBrokerAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    public function rules(): array
    {
        $max = (int) config(
            'tradex_copy_trading.max_account_number_digits',
            15
        );

        return [
            'onboarding_intent_uuid' => [
                'required',
                'uuid',
                'exists:copy_trade_onboarding_intents,uuid',
            ],
            'account_number' => [
                'required',
                'string',
                'max:'.$max,
                'regex:/^[0-9]{1,'.$max.'}$/',
            ],
        ];
    }

    public function intentUuid(): string
    {
        return (string) $this->validated('onboarding_intent_uuid');
    }

    public function accountNumber(): string
    {
        return (string) $this->validated('account_number');
    }
}
