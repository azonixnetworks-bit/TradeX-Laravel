<?php

namespace App\Http\Requests\Admin\CopyTrading;

use Illuminate\Foundation\Http\FormRequest;

class ProcessCopyTradeExternalDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin !== null
            && (
                $admin->isSuperAdmin()
                || $admin->hasPermission(
                    'copy_trading.billing.manage'
                )
            );
    }

    public function rules(): array
    {
        return [
            'balance_snapshot_uuid' => [
                'required',
                'uuid',
                'exists:copy_trade_verified_balance_snapshots,uuid',
            ],
            'deposit_amount_usd' => [
                'required',
                'string',
                'regex:/^(0|[1-9][0-9]{0,11})(\.[0-9]{1,2})?$/',
            ],
            'source_reference' => [
                'nullable',
                'string',
                'max:191',
            ],
        ];
    }

    public function depositAmountMinor(): int
    {
        $raw = trim(
            (string) $this->validated(
                'deposit_amount_usd'
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
