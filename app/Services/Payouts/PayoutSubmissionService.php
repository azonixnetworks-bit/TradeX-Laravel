<?php

namespace App\Services\Payouts;

use App\Exceptions\PayoutApiException;
use App\Models\AffiliateCommission;
use App\Models\AuditLog;
use App\Models\PayoutAllocation;
use App\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayoutSubmissionService
{
    public function __construct(
        private readonly PayoutEligibilityService $eligibility,
        private readonly PayoutBalanceService $balances,
        private readonly PayoutDestinationService $destinations,
        private readonly PayoutMoney $money
    ) {}

    public function submit(User $user,string $method,mixed $amount,?string $idempotencyKey,Request $request): array
    {
        $this->eligibility->assertCanCreate($user);
        $amountCents=$this->money->parseUsdToCents($amount);
        $minimum=max(1,(int)config('tradex_payouts.minimum_payout_cents',1500));
        if($amountCents<$minimum)throw new PayoutApiException(422,'PAYOUT_BELOW_MINIMUM','The payout amount is below the minimum payout threshold.');
        $method=strtoupper(trim($method));
        if(!in_array($method,PayoutRequest::METHODS,true))throw new PayoutApiException(422,'PAYOUT_METHOD_INVALID','The selected payout method is invalid.');
        $idempotencyHash=$this->idempotencyHash($user,$idempotencyKey);

        return DB::transaction(function()use($user,$method,$amountCents,$idempotencyHash,$request):array{
            $lockedUser=User::query()->whereKey($user->getKey())->lockForUpdate()->first();
            if(!$lockedUser instanceof User)throw new PayoutApiException(404,'PAYOUT_USER_NOT_FOUND','The user account could not be found.');
            $this->eligibility->assertCanCreate($lockedUser);
            $existing=PayoutRequest::query()->where('user_id',$lockedUser->getKey())->where('idempotency_key_hash',$idempotencyHash)->lockForUpdate()->first();
            if($existing instanceof PayoutRequest){
                if((int)$existing->amount_cents!==$amountCents||$existing->method!==$method)throw new PayoutApiException(409,'PAYOUT_IDEMPOTENCY_CONFLICT','This idempotency key was already used for a different payout request.');
                return ['payout'=>$existing,'replayed'=>true];
            }
            $available=$this->balances->availableCents($lockedUser);
            if($amountCents>$available)throw new PayoutApiException(422,'PAYOUT_INSUFFICIENT_BALANCE','The requested payout amount exceeds the available affiliate balance.');
            $snapshot=$this->destinations->snapshotFor($lockedUser,$method,true);
            $payout=PayoutRequest::query()->create([
                'user_id'=>$lockedUser->getKey(),'method'=>$method,'status'=>PayoutRequest::STATUS_PENDING_REVIEW,'amount_cents'=>$amountCents,'currency'=>'USD',
                'destination_snapshot'=>$snapshot,'destination_fingerprint'=>$this->destinations->fingerprint($snapshot),'idempotency_key_hash'=>$idempotencyHash,'requested_at'=>now(),
            ]);
            $this->allocate($lockedUser,$payout,$amountCents);
            AuditLog::record(actorType:'USER',actorId:$lockedUser->getKey(),action:'PAYOUT_REQUEST_CREATED',module:'PAYOUTS',targetType:'PAYOUT_REQUEST',targetId:$payout->getKey(),newValues:['payout_uuid'=>$payout->uuid,'method'=>$method,'status'=>PayoutRequest::STATUS_PENDING_REVIEW,'amount_cents'=>$amountCents,'currency'=>'USD'],ipAddress:$request->ip(),userAgent:$request->userAgent());
            return ['payout'=>$payout->fresh(['allocations']),'replayed'=>false];
        },3);
    }

    private function allocate(User $user,PayoutRequest $payout,int $amountCents): void
    {
        $remaining=$amountCents;
        $commissions=AffiliateCommission::query()->where('affiliate_user_id',$user->getKey())->where('status',AffiliateCommission::STATUS_EARNED)->orderBy('earned_at')->orderBy('id')->lockForUpdate()->get();
        foreach($commissions as $commission){
            if($remaining<=0)break;
            $net=(int)$commission->commission_amount_cents+(int)$commission->adjustments()->sum('amount_cents');
            $consumed=(int)PayoutAllocation::query()->join('payout_requests','payout_requests.id','=','payout_allocations.payout_request_id')->where('payout_allocations.affiliate_commission_id',$commission->getKey())->whereIn('payout_requests.status',PayoutRequest::BALANCE_CONSUMING_STATUSES)->sum('payout_allocations.amount_cents');
            $available=max(0,$net-$consumed);
            if($available<=0)continue;
            $allocation=min($remaining,$available);
            PayoutAllocation::query()->create(['payout_request_id'=>$payout->getKey(),'affiliate_commission_id'=>$commission->getKey(),'amount_cents'=>$allocation]);
            $remaining-=$allocation;
        }
        if($remaining!==0)throw new PayoutApiException(409,'PAYOUT_LEDGER_INVARIANT_FAILED','The affiliate payout ledger could not allocate the requested amount safely.');
    }

    private function idempotencyHash(User $user,?string $key): string
    {
        $key=trim((string)$key);
        if(!preg_match('/^[A-Za-z0-9._:\-]{8,128}$/',$key))throw new PayoutApiException(422,'PAYOUT_IDEMPOTENCY_KEY_INVALID','A valid Idempotency-Key header is required.');
        return hash('sha256',$user->getKey().'|'.$key);
    }
}
