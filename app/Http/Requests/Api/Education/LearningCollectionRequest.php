<?php

namespace App\Http\Requests\Api\Education;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LearningCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'view' => ['nullable', 'string', Rule::in(['CONTINUE', 'RECENT', 'COMPLETED', 'SAVED'])],
            'status' => ['nullable', 'string', Rule::in(['ALL', 'PENDING', 'SUBMITTED', 'REVIEWED'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['view', 'status'] as $field) {
            if ($this->filled($field)) {
                $this->merge([$field => strtoupper(trim((string) $this->input($field)))]);
            }
        }
    }
}
