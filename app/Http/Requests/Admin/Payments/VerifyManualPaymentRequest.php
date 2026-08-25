<?php

namespace App\Http\Requests\Admin\Payments;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

class VerifyManualPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission('payments.verify');
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
        ];
    }
}
