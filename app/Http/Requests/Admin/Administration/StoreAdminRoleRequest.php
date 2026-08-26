<?php

namespace App\Http\Requests\Admin\Administration;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
}
