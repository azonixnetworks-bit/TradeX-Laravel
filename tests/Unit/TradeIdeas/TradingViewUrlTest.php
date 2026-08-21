<?php

namespace Tests\Unit\TradeIdeas;

use App\Support\TradingViewUrl;
use PHPUnit\Framework\TestCase;

class TradingViewUrlTest extends TestCase
{
    public function test_valid_chart_and_share_urls_are_accepted(): void
    {
        $urls = [
            'https://www.tradingview.com/chart/AbCdEf12/?symbol=OANDA%3AEURUSD',
            'https://www.tradingview.com/x/AbCdEf12/',
            'https://in.tradingview.com/chart/AbCdEf12/',
        ];

        foreach ($urls as $url) {
            $this->assertNotNull(
                TradingViewUrl::normalize($url),
                $url
            );
        }
    }

    public function test_non_https_and_lookalike_hosts_are_rejected(): void
    {
        $urls = [
            'http://www.tradingview.com/chart/AbCdEf12/',
            'https://tradingview.com.evil.example/chart/AbCdEf12/',
            'https://evil.example/x/AbCdEf12/',
            'https://www.tradingview.com/markets/',
        ];

        foreach ($urls as $url) {
            $this->assertNull(
                TradingViewUrl::normalize($url),
                $url
            );
        }
    }
}
