<?php

namespace App\Http\Requests\Api\EconomicNews;

use App\Models\EconomicEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EconomicNewsIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'impact' => ['nullable', Rule::in([
                EconomicEvent::IMPACT_HIGH,
                EconomicEvent::IMPACT_MEDIUM,
                EconomicEvent::IMPACT_LOW,
            ])],
            'currency' => ['nullable', 'string', 'max:12', 'regex:/^[A-Z0-9]+$/'],
            'symbol' => ['nullable', 'string', 'max:50', 'regex:/^[A-Z0-9._-]+$/'],
            'status' => ['nullable', Rule::in([
                EconomicEvent::STATUS_SCHEDULED,
                EconomicEvent::STATUS_LIVE,
                EconomicEvent::STATUS_RELEASED,
                EconomicEvent::STATUS_REVISED,
                EconomicEvent::STATUS_CANCELLED,
            ])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['impact', 'currency', 'symbol', 'status'] as $field) {
            if (filled($this->input($field))) {
                $this->merge([$field => strtoupper(trim((string) $this->input($field)))]);
            }
        }
    }
}
