<?php

namespace App\Http\Requests\Admin\Administration;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminRoleStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission('roles.manage');
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    'ACTIVE',
                    'DISABLED',
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
