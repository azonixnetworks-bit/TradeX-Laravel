<?php

namespace App\Http\Requests\Admin\Administration;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission('admins.disable');
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    'ACTIVE',
                    'DISABLED',
                    'DELETE',
                ]),
            ],

            'reason' => [
                'required',
                'string',
                'min:5',
                'max:500',
            ],
        ];
    }
}
