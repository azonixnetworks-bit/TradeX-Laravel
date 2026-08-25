<?php

namespace App\Services\Payouts;

use App\Exceptions\PayoutApiException;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Models\UserProfile;

class PayoutDestinationService
{
    public function methods(User $user): array
    {
        $allowed=$this->allowedMethods(strtoupper(trim((string)$user->country_code)));
        $profile=UserProfile::query()->where('user_id',$user->getKey())->first();
        return array_map(function(string $method) use ($profile): array {
            $snapshot=$this->snapshotFromProfile($profile,$method,false);
            return ['code'=>$method,'configured'=>$snapshot!==null,'destination'=>$snapshot!==null?$this->masked($snapshot):null];
        },$allowed);
    }

    public function snapshotFor(User $user,string $method,bool $lock=false): array
    {
        $method=strtoupper(trim($method));
        $allowed=$this->allowedMethods(strtoupper(trim((string)$user->country_code)));
        if (!in_array($method,$allowed,true)) throw new PayoutApiException(422,'PAYOUT_METHOD_NOT_AVAILABLE','The selected payout method is not available for this country.');
        $query=UserProfile::query()->where('user_id',$user->getKey()); if($lock)$query->lockForUpdate();
        $snapshot=$this->snapshotFromProfile($query->first(),$method,true);
        if ($snapshot===null) throw new PayoutApiException(422,'PAYOUT_DESTINATION_NOT_CONFIGURED','Configure the selected payout destination in your profile first.');
        return $snapshot;
    }

    public function fingerprint(array $snapshot): string
    {
        $configuredKey=(string)config('tradex_payouts.destination_hash_key','');
        if (blank($configuredKey)) throw new PayoutApiException(500,'PAYOUT_DESTINATION_HASH_NOT_CONFIGURED','Payout destination protection is not configured.');
        $key=hash_hmac('sha256','tradex:payout:destination:v1',$configuredKey,true);
        return hash_hmac('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR),$key);
    }

    public function masked(array $snapshot): array
    {
        $method=(string)($snapshot['method']??'');
        return match($method){
            PayoutRequest::METHOD_PAYPAL,PayoutRequest::METHOD_BINANCE=>['label'=>$this->maskEmail((string)($snapshot['email']??''))],
            PayoutRequest::METHOD_USDT_TRC20=>['label'=>$this->maskWallet((string)($snapshot['address']??'')),'network'=>'TRC20'],
            PayoutRequest::METHOD_LOCAL_BANK=>['bank_name'=>(string)($snapshot['bank_name']??''),'account_holder'=>$this->maskName((string)($snapshot['account_holder']??'')),'account_number'=>$this->maskAccount((string)($snapshot['account_number']??''))],
            default=>['label'=>'Configured destination'],
        };
    }

    private function allowedMethods(string $countryCode): array
    {
        $methods=config('tradex_payouts.countries.'.$countryCode.'.methods');
        if (!is_array($methods)) throw new PayoutApiException(422,'PAYOUT_COUNTRY_NOT_SUPPORTED','Affiliate payouts are not available for this country yet.');
        return array_values(array_filter($methods,static fn(mixed $m):bool=>is_string($m)&&in_array($m,PayoutRequest::METHODS,true)));
    }

    private function snapshotFromProfile(?UserProfile $profile,string $method,bool $strict): ?array
    {
        if (!$profile instanceof UserProfile) return null;
        return match($method){
            PayoutRequest::METHOD_PAYPAL=>$this->emailSnapshot($method,$profile->paypal_email,$strict),
            PayoutRequest::METHOD_BINANCE=>$this->emailSnapshot($method,$profile->binance_email,$strict),
            PayoutRequest::METHOD_USDT_TRC20=>$this->walletSnapshot($profile->usdt_trc20_address,$strict),
            PayoutRequest::METHOD_LOCAL_BANK=>$this->bankSnapshot($profile,$strict),
            default=>null,
        };
    }

    private function emailSnapshot(string $method,mixed $email,bool $strict): ?array
    {
        $value=strtolower(trim((string)$email)); if($value==='')return null;
        if(!filter_var($value,FILTER_VALIDATE_EMAIL)){ if($strict)throw new PayoutApiException(422,'PAYOUT_DESTINATION_INVALID','The configured payout email address is invalid.'); return null; }
        return ['method'=>$method,'email'=>$value];
    }

    private function walletSnapshot(mixed $address,bool $strict): ?array
    {
        $value=trim((string)$address); if($value==='')return null;
        if(!preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/',$value)){ if($strict)throw new PayoutApiException(422,'PAYOUT_DESTINATION_INVALID','The configured USDT TRC20 address is invalid.'); return null; }
        return ['method'=>PayoutRequest::METHOD_USDT_TRC20,'network'=>'TRC20','address'=>$value];
    }

    private function bankSnapshot(UserProfile $profile,bool $strict): ?array
    {
        $values=['bank_name'=>trim((string)$profile->bank_name),'branch'=>trim((string)$profile->bank_branch),'account_holder'=>trim((string)$profile->bank_account_holder),'account_number'=>trim((string)$profile->bank_account_number)];
        foreach($values as $value) if($value==='') return null;
        if(!preg_match('/^[A-Za-z0-9\- ]{4,40}$/',$values['account_number'])){ if($strict)throw new PayoutApiException(422,'PAYOUT_DESTINATION_INVALID','The configured bank account number is invalid.'); return null; }
        return ['method'=>PayoutRequest::METHOD_LOCAL_BANK,...$values];
    }

    private function maskEmail(string $email): string { if(!str_contains($email,'@'))return '***'; [$local,$domain]=explode('@',$email,2); return substr($local,0,min(2,strlen($local))).'***@'.$domain; }
    private function maskWallet(string $value): string { return strlen($value)<=8?'****':substr($value,0,4).'...'.substr($value,-6); }
    private function maskAccount(string $value): string { return strlen($value)<=4?'****':str_repeat('*',max(4,strlen($value)-4)).substr($value,-4); }
    private function maskName(string $value): string { return $value===''?'':substr($value,0,1).'***'; }
}
