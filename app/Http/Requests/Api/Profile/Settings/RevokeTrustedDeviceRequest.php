<?php

namespace App\Http\Requests\Api\Profile\Settings;

use Illuminate\Foundation\Http\FormRequest;

class RevokeTrustedDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'totp_code' => [
                'required',
                'string',
                'digits:6',
            ],
        ];
    }
}
