<?php

namespace App\Services\Payouts;

use App\Exceptions\PayoutApiException;
use App\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PayoutQueryService
{
    public function __construct(
        private readonly PayoutEligibilityService $eligibility,
        private readonly PayoutBalanceService $balances,
        private readonly PayoutDestinationService $destinations,
        private readonly PayoutApiPresenter $presenter,
        private readonly PayoutMoney $money
    ) {}

    public function summary(User $user): array
    {
        $this->eligibility->assertCanAccess($user);
        $values=$this->balances->summaryCents($user);
        $minimum=max(1,(int)config('tradex_payouts.minimum_payout_cents',1500));
        $methods=$this->destinations->methods($user);
        $configured=array_values(array_filter($methods,static fn(array $m):bool=>(bool)($m['configured']??false)));
        $kycRequired=(bool)config('tradex_payouts.require_verified_kyc',false);
        $kycSatisfied=!$kycRequired||$user->hasVerifiedKyc();
        return [
            'currency'=>'USD',
            'gross_commission'=>$this->money->present($values['gross_commission_cents']),
            'adjustments'=>$this->money->present($values['adjustment_cents']),
            'net_earned'=>$this->money->present($values['net_earned_cents']),
            'paid'=>$this->money->present($values['paid_cents']),
            'locked'=>$this->money->present($values['locked_cents']),
            'available'=>$this->money->present($values['available_cents']),
            'adjustment_debt'=>$this->money->present($values['adjustment_debt_cents']),
            'minimum_payout'=>$this->money->present($minimum),
            'can_request_payout'=>$values['available_cents']>=$minimum&&count($configured)>0&&$kycSatisfied,
            'kyc'=>['required'=>$kycRequired,'satisfied'=>$kycSatisfied],
        ];
    }

    public function methods(User $user): array
    {
        $this->eligibility->assertCanAccess($user);
        return ['country_code'=>strtoupper((string)$user->country_code),'items'=>$this->destinations->methods($user)];
    }

    public function history(User $user,array $filters): array
    {
        $this->eligibility->assertCanAccess($user);
        $perPage=min(max(1,(int)($filters['per_page']??config('tradex_payouts.pagination.default_per_page',20))),max(1,(int)config('tradex_payouts.pagination.max_per_page',50)));
        $query=PayoutRequest::query()->where('user_id',$user->getKey())->latest('id');
        if(filled($filters['status']??null))$query->where('status',strtoupper((string)$filters['status']));
        if(filled($filters['method']??null))$query->where('method',strtoupper((string)$filters['method']));
        return $this->presentPaginator($query->paginate($perPage));
    }

    public function owned(User $user,string $uuid): PayoutRequest
    {
        $this->eligibility->assertCanAccess($user);
        $payout=PayoutRequest::query()->where('user_id',$user->getKey())->where('uuid',$uuid)->first();
        if(!$payout instanceof PayoutRequest)throw new PayoutApiException(404,'PAYOUT_NOT_FOUND','The payout request could not be found.');
        return $payout;
    }

    private function presentPaginator(LengthAwarePaginator $paginator): array
    {
        return ['items'=>collect($paginator->items())->map(fn(PayoutRequest $p):array=>$this->presenter->payout($p))->values()->all(),'pagination'=>['current_page'=>$paginator->currentPage(),'last_page'=>$paginator->lastPage(),'per_page'=>$paginator->perPage(),'total'=>$paginator->total()]];
    }
}
