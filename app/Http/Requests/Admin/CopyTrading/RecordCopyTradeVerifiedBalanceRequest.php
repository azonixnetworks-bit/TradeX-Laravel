<?php

namespace App\Http\Requests\Admin\CopyTrading;

use Illuminate\Foundation\Http\FormRequest;

class RecordCopyTradeVerifiedBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin !== null
            && (
                $admin->isSuperAdmin()
                || $admin->hasPermission(
                    'copy_trading.accounts.activate'
                )
            );
    }

    public function rules(): array
    {
        return [
            'verified_billable_balance_usd' => [
                'required',
                'string',
                'regex:/^(0|[1-9][0-9]{0,9})(\.[0-9]{1,2})?$/',
            ],
            'source_reference' => [
                'nullable',
                'string',
                'max:191',
            ],
            'observed_at' => [
                'nullable',
                'date',
                'before_or_equal:now',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'verified_billable_balance_usd.regex' =>
                'Verified Billable Balance must be a valid USD amount with at most two decimal places.',
            'observed_at.before_or_equal' =>
                'Observed time cannot be in the future.',
        ];
    }

    public function balanceMinor(): int
    {
        $raw = trim(
            (string) $this->validated(
                'verified_billable_balance_usd'
            )
        );

        [$whole, $fraction] = array_pad(
            explode('.', $raw, 2),
            2,
            ''
        );

        $fraction = str_pad(
            substr($fraction, 0, 2),
            2,
            '0'
        );

        return ((int) $whole * 100)
            + (int) $fraction;
    }
}
