<?php

namespace App\Http\Requests\Admin\CopyTrading;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCopyTradeBillingAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin !== null
            && (
                $admin->isSuperAdmin()
                || $admin->hasPermission(
                    'copy_trading.billing.adjust'
                )
            );
    }

    public function rules(): array
    {
        return [
            'billing_invoice_uuid' => [
                'nullable',
                'uuid',
                'exists:copy_trade_billing_invoices,uuid',
            ],
            'adjustment_type' => [
                'required',
                Rule::in([
                    'CORRECTION_EVIDENCE',
                    'DISPUTE_EVIDENCE',
                    'PROCESSOR_REVERSAL_EVIDENCE',
                    'CONFIRMED_BILLING_ERROR',
                ]),
            ],
            'amount_minor' => [
                'required',
                'integer',
                'between:-999999999999,999999999999',
            ],
            'reason' => [
                'required',
                'string',
                'min:10',
                'max:4000',
            ],
            'evidence_note' => [
                'nullable',
                'string',
                'max:4000',
            ],
        ];
    }
}
