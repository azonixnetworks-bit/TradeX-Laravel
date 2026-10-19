<?php

namespace App\Http\Requests\Admin\Users;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserAccountTypeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'account_type' => [
                'required',
                Rule::in(['FREE', 'PREMIUM']),
            ],
            'reason' => [
                'required',
                'string',
                'min:10',
                'max:500',
            ],
        ];
    }
}
