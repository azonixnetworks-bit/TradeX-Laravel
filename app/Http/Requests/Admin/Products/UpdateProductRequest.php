<?php

namespace App\Http\Requests\Admin\Products;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && (
                $admin->isSuperAdmin()
                || $admin->hasPermission(
                    'products.manage'
                )
            );
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:120',
            ],
            'description' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'amount_minor' => [
                'required',
                'integer',
                'min:1',
                'max:999999999',
            ],
            'is_active' => [
                'required',
                'boolean',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' =>
                    filter_var(
                        $this->input('is_active'),
                        FILTER_VALIDATE_BOOL,
                        FILTER_NULL_ON_FAILURE
                    ),
            ]);
        }
    }
}
