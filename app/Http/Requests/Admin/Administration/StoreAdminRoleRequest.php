<?php

namespace App\Http\Requests\Admin\Administration;

use App\Models\AdminPermission;
use App\Models\AdminUser;
use App\Support\Admin\AdminAccessMap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAdminRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission('roles.manage');
    }

    protected function prepareForValidation(): void
    {
        $code = strtoupper(
            trim((string) $this->input('code'))
        );

        $code = preg_replace(
            '/[^A-Z0-9_]+/',
            '_',
            $code
        ) ?: '';

        $this->merge([
            'code' => trim($code, '_'),
            'name' =>
                trim((string) $this->input('name')),
            'description' =>
                trim((string) $this->input('description')),
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'min:3',
                'max:60',
                'regex:/^[A-Z][A-Z0-9_]*$/',
                Rule::notIn([
                    'SUPER_ADMIN',
                ]),
                Rule::unique('admin_roles', 'code'),
            ],

            'name' => [
                'required',
                'string',
                'max:120',
            ],

            'description' => [
                'nullable',
                'string',
                'max:500',
            ],

            'permission_ids' => [
                'required',
                'array',
                'min:1',
                'max:200',
            ],

            'permission_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('admin_permissions', 'id'),
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
