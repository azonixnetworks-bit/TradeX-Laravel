<?php

namespace App\Services\Auth\Registration;

use App\Exceptions\AuthApiException;
use App\Models\User;

class RegistrationReferralValidator
{
    /**
     * Resolve the referrer under the same active/premium policies used by
     * registration. Never treat an unverified lookup as authorization.
     */
    public function findEligible(string $rawCode): ?User
    {
        $code = strtoupper(trim($rawCode));
        if ($code === '' || strlen($code) > 30) {
            return null;
        }

        $referrer = User::query()
            ->where('referral_code', $code)
            ->where('status', 'ACTIVE')
            ->first();

        if ($referrer === null) {
            return null;
        }

        $systemCodes = array_map(
            static fn (mixed $item): string => strtoupper(trim((string) $item)),
            config('tradex_registration.system_referral_codes', [])
        );

        if (! in_array($code, $systemCodes, true)
            && ! $referrer->canAccessPremium()) {
            return null;
        }

        return $referrer;
    }

    public function resolveOrFail(string $code): User
    {
        $referrer = $this->findEligible($code);
        if ($referrer === null) {
            throw new AuthApiException(
                422,
                'INVALID_REFERRAL_CODE',
                'The referral code is invalid or unavailable.'
            );
        }

        return $referrer;
    }
}
