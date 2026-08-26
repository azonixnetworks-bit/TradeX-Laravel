<?php

namespace App\Http\Requests\Admin\Languages;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

class ReviewTranslationValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission(
                'languages.view'
            )
            && $admin->hasPermission(
                'languages.manage'
            );
    }

    public function rules(): array
    {
        return [
            'version' => [
                'required',
                'integer',
                'min:0',
            ],

            'review_confirmation' => [
                'required',
                'accepted',
            ],
        ];
    }
}
