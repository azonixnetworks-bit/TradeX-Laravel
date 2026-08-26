<?php

namespace App\Http\Requests\Admin\Administration;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncAdminRolePermissionsRequest extends FormRequest
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
            'permission_ids' => [
                'required',
                'array',
                'min:1',
                'max:200',
            ],

            'permission_ids.*' => [
                'integer',
                'distinct',
                Rule::exists(
                    'admin_permissions',
                    'id'
                ),
            ],
        ];
    }
}
