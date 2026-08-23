<?php

namespace App\Http\Requests\Api\Auth;

class VerifyRegistrationOtpRequest extends RegistrationSessionRequest
{
    public function rules(): array
    {
        return array_merge(
            parent::rules(),
            [
                'otp_code' => [
                    'required',
                    'digits:6',
                ],
            ]
        );
    }
}
