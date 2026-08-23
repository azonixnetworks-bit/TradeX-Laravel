<?php

namespace App\Http\Requests\Api\Auth;

class ConfirmRegistrationTotpRequest extends RegistrationSessionRequest
{
    public function rules(): array
    {
        return array_merge(
            parent::rules(),
            [
                'totp_code' => [
                    'required',
                    'digits:6',
                ],
            ]
        );
    }
}
