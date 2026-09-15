<?php

namespace Tests\Unit\Trading;

use App\Services\Trading\TradingInstrumentCatalog;
use Tests\TestCase;

class BinanceFuturesCatalogUiTest extends TestCase
{
    public function test_binance_futures_market_is_exposed_with_planning_metadata(): void
    {
        $market = app(TradingInstrumentCatalog::class)->markets()['binance_futures'];

        $this->assertSame('Binance Futures', $market['label']);
        $this->assertSame('Binance', $market['broker']);
        $this->assertSame('PARTIALLY VERIFIED', $market['spec_status']);
        $this->assertSame(10, $market['instrument_count']);
        $this->assertSame(1, $market['leverage']['min']);
        $this->assertSame(125, $market['leverage']['max']);
        $this->assertSame(10, $market['leverage']['default']);
        $this->assertTrue($market['leverage']['tier_dependent']);
        $this->assertSame('Risk Based', $market['calculation_modes']['RISK_BASED']);
        $this->assertSame('Margin Based', $market['calculation_modes']['MARGIN_BASED']);

        foreach (['BTCUSDT', 'ETHUSDT', 'BNBUSDT', 'SOLUSDT', 'XRPUSDT', 'DOGEUSDT'] as $symbol) {
            $this->assertArrayHasKey($symbol, $market['instruments']);
            $this->assertSame('USDT', $market['instruments'][$symbol]['quote_currency']);
            $this->assertSame('USDⓈ-M Perpetual', $market['instruments'][$symbol]['category']);
        }
    }

    public function test_binance_futures_ui_exposes_mode_leverage_margin_and_backend_only_calculation(): void
    {
        $view = file_get_contents(base_path(
            'resources/views/admin/trading_tools/risk_reward/index.blade.php'
        ));
        $script = file_get_contents(base_path(
            'public/admin/js/risk-reward-binance-futures.js'
        ));

        $this->assertStringContainsString('risk-reward-binance-futures.js', $view);
        $this->assertStringContainsString('riskRewardCalculationMode', $script);
        $this->assertStringContainsString('RISK_BASED', $script);
        $this->assertStringContainsString('MARGIN_BASED', $script);
        $this->assertStringContainsString('riskRewardLeverage', $script);
        $this->assertStringContainsString('riskRewardMarginAmount', $script);
        $this->assertStringContainsString('margin_amount', $script);
        $this->assertStringContainsString('data.futures', $script);
        $this->assertStringContainsString('fetch(root.dataset.calculateEndpoint', $script);
        $this->assertStringNotContainsString('margin * leverage', strtolower($script));
        $this->assertStringNotContainsString('risk /', strtolower($script));
    }
}
