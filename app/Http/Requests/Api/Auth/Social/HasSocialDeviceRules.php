<?php

namespace App\Http\Requests\Api\Auth\Social;

use App\Models\UserDevice;
use Illuminate\Validation\Rule;

trait HasSocialDeviceRules
{
    protected function socialDeviceRules(): array
    {
        return [
            'installation_id' => ['required', 'uuid'],
            'platform' => [
                'required', 'string',
                Rule::in([
                    UserDevice::PLATFORM_ANDROID,
                    UserDevice::PLATFORM_IOS,
                    UserDevice::PLATFORM_WINDOWS,
                    UserDevice::PLATFORM_MACOS,
                    UserDevice::PLATFORM_WEB,
                ]),
            ],
            'device_name' => ['nullable', 'string', 'max:150'],
            'device_model' => ['nullable', 'string', 'max:150'],
            'os_version' => ['nullable', 'string', 'max:100'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'device_public_key' => ['nullable', 'string', 'max:10000'],
        ];
    }

    protected function normalizePlatform(): void
    {
        $this->merge([
            'platform' => strtoupper(trim((string) $this->input('platform'))),
        ]);
    }
}
