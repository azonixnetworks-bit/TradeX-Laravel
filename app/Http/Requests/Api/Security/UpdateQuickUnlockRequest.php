<?php

namespace App\Http\Requests\Api\Security;

use App\Models\UserDeviceQuickUnlockSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuickUnlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enabled' => [
                'required',
                'boolean',
            ],

            'method' => [
                'nullable',
                Rule::requiredIf(
                    $this->boolean('enabled')
                ),
                'string',
                Rule::in(
                    UserDeviceQuickUnlockSetting::METHODS
                ),
            ],

            'totp_code' => [
                'required',
                'digits:6',
            ],

            /*
             * MOBILE-SEC-01 server contract:
             * local PIN and biometric material must never cross the network.
             */
            'pin' => ['prohibited'],
            'pin_hash' => ['prohibited'],
            'pin_salt' => ['prohibited'],
            'biometric' => ['prohibited'],
            'biometric_data' => ['prohibited'],
            'fingerprint' => ['prohibited'],
            'face_id' => ['prohibited'],
            'local_secret' => ['prohibited'],
            'refresh_token' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('method')) {
            $this->merge([
                'method' =>
                    strtoupper(
                        trim(
                            (string) $this->input(
                                'method'
                            )
                        )
                    )
                    ?: null,
            ]);
        }
    }
}
