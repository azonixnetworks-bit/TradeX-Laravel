<?php

use App\Models\PayoutRequest;

return [
    'currency'=>'USD',
    'minimum_payout_cents'=>(int) env('TRADEX_PAYOUT_MINIMUM_CENTS', 1500),
    'require_verified_kyc'=>(bool) env('TRADEX_PAYOUT_REQUIRE_VERIFIED_KYC', false),
    'destination_hash_key'=>env('TRADEX_PAYOUT_DESTINATION_HASH_KEY', env('APP_KEY')),
    'countries'=>[
        'LK'=>['methods'=>[PayoutRequest::METHOD_PAYPAL,PayoutRequest::METHOD_BINANCE,PayoutRequest::METHOD_USDT_TRC20,PayoutRequest::METHOD_LOCAL_BANK]],
        'IN'=>['methods'=>[PayoutRequest::METHOD_PAYPAL,PayoutRequest::METHOD_BINANCE,PayoutRequest::METHOD_USDT_TRC20]],
    ],
    'pagination'=>['default_per_page'=>(int) env('TRADEX_PAYOUTS_PER_PAGE',20),'max_per_page'=>(int) env('TRADEX_PAYOUTS_MAX_PER_PAGE',50)],
    'rate_limits'=>['read_per_minute'=>(int) env('TRADEX_PAYOUTS_READ_PER_MINUTE',60),'create_per_minute'=>(int) env('TRADEX_PAYOUTS_CREATE_PER_MINUTE',5),'create_per_hour'=>(int) env('TRADEX_PAYOUTS_CREATE_PER_HOUR',10)],
];
