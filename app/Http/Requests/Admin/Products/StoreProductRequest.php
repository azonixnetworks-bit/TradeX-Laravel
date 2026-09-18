<?php

namespace App\Http\Requests\Admin\Products;

use App\Models\AdminUser;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9_]+$/',
                Rule::unique('products', 'code'),
            ],
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
            'billing_type' => [
                'required',
                Rule::in([
                    Product::BILLING_ONE_TIME_LIFETIME,
                ]),
            ],
            'currency' => [
                'required',
                Rule::in(['USD']),
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
        $this->merge([
            'code' => strtoupper(
                trim((string) $this->input('code'))
            ),
            'billing_type' => strtoupper(
                trim((string) $this->input('billing_type'))
            ),
            'currency' => strtoupper(
                trim((string) $this->input('currency'))
            ),
        ]);

        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var(
                    $this->input('is_active'),
                    FILTER_VALIDATE_BOOL,
                    FILTER_NULL_ON_FAILURE
                ),
            ]);
        }
    }
}