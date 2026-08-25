<?php

namespace App\Http\Requests\Api\Auth\PasswordRecovery;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPasswordRecoveryOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $digits = max(
            4,
            min(
                8,
                (int) config(
                    'tradex_password_recovery.otp.digits',
                    6
                )
            )
        );

        return [
            'recovery_id' => [
                'required',
                'uuid',
            ],
            'recovery_token' => [
                'required',
                'string',
                'min:32',
                'max:200',
            ],
            'otp_code' => [
                'required',
                'digits:'.$digits,
            ],
        ];
    }
}
