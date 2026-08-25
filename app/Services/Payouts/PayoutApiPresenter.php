<?php

namespace App\Services\Payouts;

use App\Models\PayoutRequest;

class PayoutApiPresenter
{
    public function __construct(private readonly PayoutMoney $money, private readonly PayoutDestinationService $destinations) {}
    public function payout(PayoutRequest $payout): array
    {
        $snapshot=is_array($payout->destination_snapshot)?$payout->destination_snapshot:[];
        return [
            'uuid'=>$payout->uuid,'method'=>$payout->method,'status'=>$payout->status,
            'amount'=>$this->money->present((int)$payout->amount_cents,(string)$payout->currency),
            'destination'=>$this->destinations->masked($snapshot),
            'provider_reference'=>$payout->isPaid()?$payout->provider_reference:null,
            'reason'=>$payout->user_visible_reason,
            'requested_at'=>$payout->requested_at?->toIso8601String(),'approved_at'=>$payout->approved_at?->toIso8601String(),'processing_at'=>$payout->processing_at?->toIso8601String(),'paid_at'=>$payout->paid_at?->toIso8601String(),'rejected_at'=>$payout->rejected_at?->toIso8601String(),'failed_at'=>$payout->failed_at?->toIso8601String(),'cancelled_at'=>$payout->cancelled_at?->toIso8601String(),
        ];
    }
}
