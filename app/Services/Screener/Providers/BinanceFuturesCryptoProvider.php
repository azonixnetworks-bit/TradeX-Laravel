<?php

namespace App\Services\Screener\Providers;

class BinanceFuturesCryptoProvider extends BinanceCryptoProvider
{
    public function name(): string
    {
        return 'BINANCE_FUTURES';
    }

    public function market(): string
    {
        return 'CRYPTO_FUTURES';
    }

    protected function settingsKey(): string
    {
        return 'tradex_screener.providers.binance_futures';
    }

    protected function klinesPath(): string
    {
        return '/fapi/v1/klines';
    }

    protected function healthCacheKey(): string
    {
        return 'tradex:screener:provider:binance-futures:health';
    }
}
