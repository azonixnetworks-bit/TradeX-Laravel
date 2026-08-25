<?php

namespace App\Services\Payouts;

use App\Exceptions\PayoutApiException;

class PayoutMoney
{
    public function parseUsdToCents(mixed $value): int
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) throw new PayoutApiException(422,'PAYOUT_AMOUNT_INVALID','The payout amount is invalid.');
        $amount=trim((string)$value);
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/',$amount)) throw new PayoutApiException(422,'PAYOUT_AMOUNT_INVALID','The payout amount must use at most two decimal places.');
        [$whole,$decimal]=array_pad(explode('.',$amount,2),2,'');
        $decimal=str_pad($decimal,2,'0');
        $cents=((int)$whole*100)+(int)substr($decimal,0,2);
        if ($cents<=0) throw new PayoutApiException(422,'PAYOUT_AMOUNT_INVALID','The payout amount must be greater than zero.');
        return $cents;
    }

    public function present(int $cents, string $currency='USD'): array
    {
        $negative=$cents<0; $absolute=abs($cents);
        $amount=intdiv($absolute,100).'.'.str_pad((string)($absolute%100),2,'0',STR_PAD_LEFT);
        if ($negative) $amount='-'.$amount;
        return ['amount'=>$amount,'amount_cents'=>$cents,'currency'=>strtoupper($currency)];
    }
}
