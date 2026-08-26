<?php

namespace App\Http\Requests\Api\Legal;

use Illuminate\Foundation\Http\FormRequest;

class AcceptLegalPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'content_sha256' => strtolower(
                trim((string) $this->input('content_sha256'))
            ),
        ]);
    }

    public function rules(): array
    {
        return [
            'content_sha256' => [
                'required',
                'string',
                'regex:/\A[a-f0-9]{64}\z/',
            ],
            'acceptance_confirmation' => [
                'required',
                'accepted',
            ],
        ];
    }
}
