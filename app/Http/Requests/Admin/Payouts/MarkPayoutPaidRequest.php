<?php

namespace App\Http\Requests\Admin\Payouts;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

class MarkPayoutPaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission('payouts.mark_paid');
    }

    public function rules(): array
    {
        return [
            'provider_reference' => [
                'required',
                'string',
                'min:4',
                'max:190',
                'not_regex:/[\r\n]/',
            ],
            'internal_note' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
