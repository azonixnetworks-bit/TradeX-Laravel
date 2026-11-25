<?php

namespace App\Http\Requests\Api\Payments;

use Illuminate\Foundation\Http\FormRequest;

class CreatePayPalOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' =>
                $this->header(
                    'Idempotency-Key'
                ),
            'product_uuid' => filled(
                $this->input('product_uuid')
            )
                ? trim((string) $this->input('product_uuid'))
                : null,
            'affiliate_code' => filled($this->input('affiliate_code'))
                ? strtoupper(trim((string) $this->input('affiliate_code')))
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => [
                'required',
                'string',
                'min:16',
                'max:128',
                'regex:/^[A-Za-z0-9._:-]+$/',
            ],
            'product_uuid' => [
                'nullable',
                'uuid',
                'exists:products,uuid',
            ],
            'affiliate_code' => ['nullable', 'string', 'max:30'],
        ];
    }
}
