<?php

namespace App\Http\Requests\Admin\Administration;

use App\Models\AdminPermission;
use App\Models\AdminUser;
use App\Support\Admin\AdminAccessMap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $permissionIds = collect(
                (array) $this->input('permission_ids', [])
            )
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $permissionCodes = AdminPermission::query()
                ->whereIn('id', $permissionIds)
                ->pluck('code')
                ->all();

            foreach (
                AdminAccessMap::missingDependencies($permissionCodes)
                as $permission => $missing
            ) {
                $validator->errors()->add(
                    'permission_ids',
                    $permission.' requires '.implode(', ', $missing).'.'
                );
            }
        });
    }
}
