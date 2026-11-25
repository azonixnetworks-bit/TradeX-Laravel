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
            'lkr_amount_minor' => [
                'nullable',
                'integer',
                'min:1',
                'max:999999999999',
            ],
            'affiliate_discount_percent' => [
                'nullable',
                'integer',
                'min:0',
                'max:100',
            ],
            'is_active' => [
                'required',
                'boolean',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('regular_price_usd')) {
            $this->merge([
                'amount_minor' => $this->toMinor(
                    (string) $this->input('regular_price_usd')
                ),
            ]);
        }

        if ($this->filled('regular_price_lkr')) {
            $this->merge([
                'lkr_amount_minor' => $this->toMinor(
                    (string) $this->input('regular_price_lkr')
                ),
            ]);
        }

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

    private function toMinor(string $value): ?int
    {
        $value = trim(str_replace(',', '', $value));

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ((int) $whole * 100)
            + (int) str_pad($fraction, 2, '0');
    }
}
