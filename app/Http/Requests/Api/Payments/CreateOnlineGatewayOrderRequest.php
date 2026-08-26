<?php

namespace App\Http\Requests\Api\Payments;

use Illuminate\Foundation\Http\FormRequest;

class CreateOnlineGatewayOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
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
        ];
    }
}
