<?php

namespace App\Http\Requests\Api\Auth\PasswordRecovery;

use App\Support\Security\BrandedEmailOtp;

class VerifyPasswordRecoveryEmailOtpRequest extends VerifyPasswordRecoveryOtpRequest
{
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
        $rules = parent::rules();

        $rules['otp_code'] = [
            'required',
            'string',
            'regex:'.BrandedEmailOtp::emailValidationRule(),
        ];

        return $rules;
    }
}