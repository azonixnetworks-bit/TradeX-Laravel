<?php

namespace App\Http\Requests\Api\Auth\Social;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SocialChallengeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'purpose' => [
                'required', 'string',
                Rule::in(['LOGIN', 'ONBOARD', 'ONBOARD_CONFIRM', 'LINK']),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['purpose' => strtoupper(trim((string) $this->input('purpose')))]);
    }
}
