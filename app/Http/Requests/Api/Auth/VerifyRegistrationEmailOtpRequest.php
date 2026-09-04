<?php

namespace App\Http\Requests\Api\Auth;

use App\Support\Security\BrandedEmailOtp;

class VerifyRegistrationEmailOtpRequest extends RegistrationSessionRequest
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
        return array_merge(
            parent::rules(),
            [
                'otp_code' => [
                    'required',
                    'string',
                    'regex:'.BrandedEmailOtp::emailValidationRule(),
                ],
            ]
        );
    }
}