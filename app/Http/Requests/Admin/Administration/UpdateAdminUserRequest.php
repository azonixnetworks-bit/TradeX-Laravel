<?php

namespace App\Http\Requests\Admin\Administration;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission('admins.update');
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
        $target = $this->route('adminUser');

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
                Rule::unique('admin_users', 'email')
                    ->ignore(
                        $target?->getKey()
                    ),
            ],
        ];
    }
}
