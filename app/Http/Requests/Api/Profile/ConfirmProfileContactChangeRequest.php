<?php

namespace App\Http\Requests\Api\Profile;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmProfileContactChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
                'digits:6',
            ],
        ];
    }
}
