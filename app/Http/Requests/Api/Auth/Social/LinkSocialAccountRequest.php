<?php

namespace App\Http\Requests\Api\Auth\Social;

use Illuminate\Foundation\Http\FormRequest;

class LinkSocialAccountRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'credential' => ['required', 'string', 'max:'.(int) config('tradex_social_auth.credential_max_length', 20000)],
            'challenge_id' => ['nullable', 'uuid'],
            'totp_code' => ['required', 'digits:6'],
        ];
    }
}
