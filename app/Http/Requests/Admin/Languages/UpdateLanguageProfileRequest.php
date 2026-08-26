<?php

namespace App\Http\Requests\Admin\Languages;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLanguageProfileRequest extends FormRequest
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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_translation_enabled' =>
                filter_var(
                    $this->input(
                        'is_translation_enabled',
                        false
                    ),
                    FILTER_VALIDATE_BOOL
                ),
        ]);
    }

    public function rules(): array
    {
        return [
            'is_translation_enabled' => [
                'required',
                'boolean',
            ],

            'version' => [
                'required',
                'integer',
                'min:0',
            ],
        ];
    }
}
