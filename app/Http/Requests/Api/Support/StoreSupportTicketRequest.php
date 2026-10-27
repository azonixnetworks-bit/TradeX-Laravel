<?php

namespace App\Http\Requests\Api\Support;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupportTicketRequest extends FormRequest
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
        return [
            'category_uuid' => ['required', 'uuid', Rule::exists('support_categories', 'uuid')->where('enabled', true)],
            'subject' => ['required', 'string', 'min:5', 'max:180'],
            'description' => ['required', 'string', 'min:10', 'max:10000'],
            'requested_urgency' => ['nullable', Rule::in(['low', 'normal', 'high'])],
            'intake' => ['sometimes', 'array', 'max:25'],
            'intake.*' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ];
    }

    public function idempotencyKey(): string
    {
        return (string) ($this->header('Idempotency-Key') ?: $this->input('idempotency_key') ?: '');
    }
}
