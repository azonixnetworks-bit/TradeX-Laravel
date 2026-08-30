<?php

namespace App\Http\Requests\Api\Auth;

use App\Models\UserDevice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
            ],

            'password' => [
                'required',
                'string',
                'max:1024',
            ],

            'totp_code' => [
                'nullable',
                'digits:6',
                'required_without:recovery_code',
                'prohibits:recovery_code',
            ],

            'recovery_code' => [
                'nullable',
                'string',
                'max:80',
                'required_without:totp_code',
                'prohibits:totp_code',
            ],

            'installation_id' => [
                'required',
                'uuid',
            ],

            'platform' => [
                'required',
                'string',
                Rule::in([
                    UserDevice::PLATFORM_ANDROID,
                    UserDevice::PLATFORM_IOS,
                    UserDevice::PLATFORM_WINDOWS,
                    UserDevice::PLATFORM_MACOS,
                    UserDevice::PLATFORM_WEB,
                ]),
            ],

            'device_name' => [
                'nullable',
                'string',
                'max:150',
            ],

            'device_model' => [
                'nullable',
                'string',
                'max:150',
            ],

            'os_version' => [
                'nullable',
                'string',
                'max:100',
            ],

            'app_version' => [
                'nullable',
                'string',
                'max:50',
            ],

            'device_public_key' => [
                'nullable',
                'string',
                'max:10000',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $recoveryCode = $this->input('recovery_code');

        $this->merge([
            'email' => mb_strtolower(
                trim((string) $this->input('email'))
            ),
            'platform' => strtoupper(
                trim((string) $this->input('platform'))
            ),
            'recovery_code' => is_string($recoveryCode)
                ? trim($recoveryCode)
                : $recoveryCode,
        ]);
    }
}
