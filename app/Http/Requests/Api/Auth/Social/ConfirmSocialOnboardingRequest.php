<?php

namespace App\Http\Requests\Api\Auth\Social;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmSocialOnboardingRequest extends FormRequest
{
    use HasSocialDeviceRules;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return array_merge([
            'registration_id' => ['required', 'uuid'],
            'registration_token' => ['required', 'string', 'size:64'],
            'credential' => ['required', 'string', 'max:'.(int) config('tradex_social_auth.credential_max_length', 20000)],
            'challenge_id' => ['nullable', 'uuid'],
            'totp_code' => ['required', 'digits:6'],
        ], $this->socialDeviceRules());
    }

    protected function prepareForValidation(): void { $this->normalizePlatform(); }
}
