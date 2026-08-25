<?php

namespace App\Services\Payouts;

use App\Models\AffiliateCommission;
use App\Models\AffiliateCommissionAdjustment;
use App\Models\PayoutAllocation;
use App\Models\PayoutRequest;
use App\Models\User;

class PayoutBalanceService
{
    public function summaryCents(User $user): array
    {
        $gross=(int)AffiliateCommission::query()->where('affiliate_user_id',$user->getKey())->where('status',AffiliateCommission::STATUS_EARNED)->sum('commission_amount_cents');
        $adjustments=(int)AffiliateCommissionAdjustment::query()->where('affiliate_user_id',$user->getKey())->sum('amount_cents');
        $net=$gross+$adjustments;
        $paid=$this->allocatedForStatuses($user,[PayoutRequest::STATUS_PAID]);
        $locked=$this->allocatedForStatuses($user,PayoutRequest::ACTIVE_STATUSES);
        $raw=$net-$paid-$locked;
        return ['gross_commission_cents'=>$gross,'adjustment_cents'=>$adjustments,'net_earned_cents'=>$net,'paid_cents'=>$paid,'locked_cents'=>$locked,'available_cents'=>max(0,$raw),'adjustment_debt_cents'=>max(0,-$raw)];
    }
    public function availableCents(User $user): int { return (int)$this->summaryCents($user)['available_cents']; }
    private function allocatedForStatuses(User $user,array $statuses): int
    {
        return (int)PayoutAllocation::query()->join('payout_requests','payout_requests.id','=','payout_allocations.payout_request_id')->where('payout_requests.user_id',$user->getKey())->whereIn('payout_requests.status',$statuses)->sum('payout_allocations.amount_cents');
    }
}
