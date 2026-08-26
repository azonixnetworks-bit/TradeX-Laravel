<?php

namespace App\Http\Requests\Admin\Administration;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission('admins.create')
            && $admin->hasPermission('roles.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' =>
                trim((string) $this->input('name')),

            'email' =>
                strtolower(
                    trim((string) $this->input('email'))
                ),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'email' => [
                'required',
                'email:rfc',
                'max:190',
                Rule::unique('admin_users', 'email'),
            ],

            'password' => [
                'required',
                'confirmed',
                Password::min(16)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],

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
