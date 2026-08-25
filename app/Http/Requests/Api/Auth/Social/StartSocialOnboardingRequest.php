<?php

namespace App\Http\Requests\Api\Auth\Social;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartSocialOnboardingRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'credential' => ['required', 'string', 'max:'.(int) config('tradex_social_auth.credential_max_length', 20000)],
            'challenge_id' => ['nullable', 'uuid'],
            'name' => ['nullable', 'string', 'min:2', 'max:120'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'whatsapp_number' => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/', 'max:30'],
            'referral_code' => ['nullable', 'string', 'max:30'],
            'country_code' => ['required', 'string', 'size:2', 'alpha'],
            'language_code' => [
                'required', 'string',
                Rule::in(config('tradex_registration.allowed_languages', ['en'])),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')) ?: null,
            'email' => mb_strtolower(trim((string) $this->input('email'))) ?: null,
            'whatsapp_number' => preg_replace('/[\s\-\(\)]+/', '', (string) $this->input('whatsapp_number')),
            'referral_code' => strtoupper(trim((string) $this->input('referral_code'))) ?: null,
            'country_code' => strtoupper(trim((string) $this->input('country_code'))),
            'language_code' => strtolower(trim((string) $this->input('language_code'))),
        ]);
    }
}
