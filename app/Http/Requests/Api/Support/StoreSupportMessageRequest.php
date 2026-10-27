<?php

namespace App\Http\Requests\Api\Support;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupportMessageRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->hasHeader('Idempotency-Key')) {
            $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
        }
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['message' => ['required', 'string', 'max:10000'], 'idempotency_key' => ['required', 'string', 'max:100']];
    }

    public function idempotencyKey(): string
    {
        return (string) ($this->header('Idempotency-Key') ?: $this->input('idempotency_key') ?: '');
    }
}
