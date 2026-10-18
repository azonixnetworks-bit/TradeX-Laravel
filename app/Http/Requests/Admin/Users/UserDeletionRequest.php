<?php

namespace App\Http\Requests\Admin\Users;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserDeletionRequest extends FormRequest
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
            'reason' => [
                'required',
                'string',
                'min:10',
                'max:500',
            ],
            'confirmation' => [
                'required',
                'string',
                Rule::in(['DELETE USER']),
            ],
        ];
    }
}
