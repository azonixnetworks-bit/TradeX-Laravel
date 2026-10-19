<?php

namespace App\Services\Security\QuickUnlock;

use App\Models\UserDeviceQuickUnlockSetting;

class QuickUnlockPolicyService
{
    public function policy(): array
    {
        return [
            'version' => (string) config(
                'tradex_mobile_security.quick_unlock.policy_version',
                'MOBILE-SEC-01-v1'
            ),

            'allowed_methods' => [
                UserDeviceQuickUnlockSetting::METHOD_PIN,
                UserDeviceQuickUnlockSetting::METHOD_BIOMETRIC,
                UserDeviceQuickUnlockSetting::METHOD_BOTH,
            ],

            'pin_length' => (int) config(
                'tradex_mobile_security.quick_unlock.pin_length',
                4
            ),

            'max_failed_pin_attempts' => max(
                1,
                (int) config(
                    'tradex_mobile_security.quick_unlock.max_failed_pin_attempts',
                    5
                )
            ),

            'lockout_seconds' => max(
                0,
                (int) config(
                    'tradex_mobile_security.quick_unlock.lockout_seconds',
                    300
                )
            ),

            'background_lock_seconds' => max(
                0,
                (int) config(
                    'tradex_mobile_security.quick_unlock.background_lock_seconds',
                    30
                )
            ),

            'inactivity_lock_seconds' => max(
                0,
                (int) config(
                    'tradex_mobile_security.quick_unlock.inactivity_lock_seconds',
                    300
                )
            ),

            'biometric_allowed' => (bool) config(
                'tradex_mobile_security.quick_unlock.biometric_allowed',
                true
            ),

            'secure_storage_required' => true,

            'pin_transport' => 'NEVER_SEND_TO_SERVER',

            'biometric_transport' => 'NEVER_SEND_TO_SERVER',

            'refresh_endpoint' => '/api/v1/auth/refresh',

            'challenge_endpoint' => '/api/v1/auth/quick-unlock/challenge',

            'verification_endpoint' => '/api/v1/auth/quick-unlock/verify',

            'credential_algorithm' => DeviceCredentialVerifier::ALGORITHM_ES256,

            'compromised_device_action' => (string) config(
                'tradex_mobile_security.quick_unlock.compromised_device_action',
                'BLOCK_QUICK_UNLOCK'
            ),
        ];
    }
}
