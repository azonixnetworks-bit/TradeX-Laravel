<?php

namespace App\Services\Payouts;

use App\Exceptions\PayoutApiException;
use App\Models\User;

class PayoutEligibilityService
{
    public function assertCanAccess(User $user): void
    {
        if (!$user->canAccessPremium()) throw new PayoutApiException(403,'PAYOUT_PREMIUM_REQUIRED','Premium access is required to use affiliate payouts.');
    }

    public function assertCanCreate(User $user): void
    {
        $this->assertCanAccess($user);
        if ((bool)config('tradex_payouts.require_verified_kyc',false) && !$user->hasVerifiedKyc()) throw new PayoutApiException(403,'PAYOUT_KYC_REQUIRED','Verified KYC is required before requesting a payout.');
    }
}
