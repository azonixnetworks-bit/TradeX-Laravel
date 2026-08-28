<?php

namespace App\Http\Requests\Api\CopyTrading;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ResubmitCopyTradeBrokerAccountRequest extends FormRequest
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
            'account_number' => [
                'required',
                'string',
                'max:'.$max,
                'regex:/^[0-9]{1,'.$max.'}$/',
            ],
        ];
    }

    public function accountNumber(): string
    {
        return (string) $this->validated('account_number');
    }
}
