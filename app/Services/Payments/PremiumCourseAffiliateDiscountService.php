<?php

namespace App\Services\Payments;

use App\Exceptions\AuthApiException;
use App\Models\Product;
use App\Models\User;
use App\Services\Auth\Registration\RegistrationReferralValidator;
use Illuminate\Support\Facades\DB;

class PremiumCourseAffiliateDiscountService
{
    public function __construct(
        private readonly RegistrationReferralValidator $referrals
    ) {}

    public function apply(
        User $customer,
        int $regularAmountMinor,
        ?string $affiliateCode
    ): int {
        if (blank($affiliateCode)) {
            return $regularAmountMinor;
        }

        $affiliate = $this->referrals->resolveOrFail($affiliateCode);

        if ((int) $affiliate->getKey() === (int) $customer->getKey()) {
            throw new AuthApiException(
                422,
                'SELF_REFERRAL_NOT_ALLOWED',
                'You cannot use your own affiliate code.'
            );
        }

        DB::transaction(function () use ($customer, $affiliate): void {
            $lockedCustomer = User::query()
                ->whereKey($customer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedCustomer->referred_by_user_id !== null
                && (int) $lockedCustomer->referred_by_user_id
                    !== (int) $affiliate->getKey()
            ) {
                throw new AuthApiException(
                    422,
                    'AFFILIATE_CODE_CONFLICT',
                    'This account is already linked to another affiliate.'
                );
            }

            if ($lockedCustomer->referred_by_user_id === null) {
                $lockedCustomer->forceFill([
                    'referred_by_user_id' => $affiliate->getKey(),
                ])->save();
            }
        }, 3);

        return $this->discountedAmount($regularAmountMinor);
    }

    public function discountedAmount(int $regularAmountMinor): int
    {
        $discountPercent = max(
            0,
            min(
                100,
                (int) Product::query()
                    ->where('code', Product::CODE_EDUCATION_PREMIUM)
                    ->value('affiliate_discount_percent')
            )
        );

        return intdiv(
            ($regularAmountMinor * (100 - $discountPercent)) + 50,
            100
        );
    }
}
