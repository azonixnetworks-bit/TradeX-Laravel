<?php

namespace App\Http\Requests\Admin\Investments;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

class RejectInvestmentDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission(
                'investments.deposits.verify'
            );
    }

    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'min:5',
                'max:1000',
            ],
        ];
    }
}
