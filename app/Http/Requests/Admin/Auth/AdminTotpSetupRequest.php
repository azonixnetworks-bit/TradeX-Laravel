<?php

namespace App\Http\Requests\Admin\Auth;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

class AdminTotpSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->user()
            instanceof AdminUser;
    }

    public function rules(): array
    {
        return [
            'current_password' => [
                'required',
                'string',
                'current_password:admin',
            ],
        ];
    }
}
