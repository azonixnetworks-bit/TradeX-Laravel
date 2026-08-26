<?php

namespace App\Http\Requests\Admin\Administration;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncAdminUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission('admins.update')
            && $admin->hasPermission('roles.manage');
    }

    public function rules(): array
    {
        return [
            'role_ids' => [
                'required',
                'array',
                'min:1',
                'max:20',
            ],

            'role_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('admin_roles', 'id')
                    ->where(
                        fn ($query) =>
                            $query
                                ->where('is_active', true)
                                ->where('code', '!=', 'SUPER_ADMIN')
                    ),
            ],
        ];
    }
}
