<?php

namespace App\Http\Requests\Admin\CopyTrading;

use Illuminate\Foundation\Http\FormRequest;

class LinkCopyTradeBillingPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin !== null
            && (
                $admin->isSuperAdmin()
                || (
                    $admin->hasPermission(
                        'copy_trading.billing.manage'
                    )
                    && $admin->hasPermission(
                        'payments.reconcile'
                    )
                )
            );
    }

    public function rules(): array
    {
        return [
            'payment_uuid' => [
                'required',
                'uuid',
                'exists:payments,uuid',
            ],
        ];
    }
}
