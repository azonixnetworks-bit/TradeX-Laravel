<?php

namespace App\Http\Requests\Admin\Payouts;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

class ApprovePayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission('payouts.approve');
    }

    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'min:10',
                'max:500',
            ],
            'internal_note' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
