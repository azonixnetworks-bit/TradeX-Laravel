<?php

namespace App\Http\Requests\Api\Profile;

use App\Support\Security\BrandedEmailOtp;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmProfileContactChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'otp_code' => BrandedEmailOtp::normalize(
                $this->input('otp_code')
            ),
        ]);
    }

    public function rules(): array
    {
        return [
            'challenge_id' => [
                'required',
                'uuid',
            ],

            'challenge_token' => [
                'required',
                'string',
                'size:64',
            ],

            'otp_code' => [
                'required',
                'string',
                'regex:'.BrandedEmailOtp::emailValidationRule(),
            ],
        ];
    }
}
