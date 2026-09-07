<?php

namespace Tests\Feature\Api\Screener;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class ScreenerHealthApiTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::fake();

        config()->set('tradex_screener.enabled', true);
        config()->set('tradex_screener.providers.twelve_data.enabled', true);
        config()->set(
            'tradex_screener.providers.twelve_data.api_key',
            'health-test-forex-secret'
        );
        config()->set('tradex_screener.providers.binance.enabled', true);
        config()->set(
            'tradex_screener.providers.binance.base_url',
            'https://data-api.binance.vision'
        );
        config()->set('tradex_screener.providers.deriv.enabled', true);
        config()->set(
            'tradex_screener.providers.deriv.websocket_url',
            'wss://api.derivws.com/trading/v1/options/ws/public'
        );

        config()->set('tradex_screener_stocks.enabled', true);
        config()->set('tradex_screener_stocks.provider.enabled', true);
        config()->set(
            'tradex_screener_stocks.provider.external_display_approved',
            true
        );
        config()->set(
            'tradex_screener_stocks.provider.api_key',
            'health-test-stocks-secret'
        );

        $this->markProvidersHealthy();
    }

    public function test_health_endpoint_requires_mobile_authentication(): void
    {
        $this->getJson(
            '/api/v1/screener/health'
        )->assertUnauthorized();
    }

    public function test_mobile_user_can_read_secret_safe_unified_provider_health(): void
    {
        Cache::put(
            'tradex:screener:fresh:v1:FOREX:H1',
            ['generated_at_utc' => now('UTC')->toIso8601String(), 'rows' => []],
            60
        );
        Cache::put(
            'tradex:screener:stale:v1:FOREX:H1',
            ['generated_at_utc' => now('UTC')->toIso8601String(), 'rows' => []],
            900
        );

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $response = $this->withToken($token)
            ->getJson('/api/v1/screener/health');

        $response
            ->assertOk()
            ->assertJsonPath('data.phase', 'SCREENER-05')
            ->assertJsonPath('data.overall_status', 'HEALTHY')
            ->assertJsonPath('data.active_market_count', 4)
            ->assertJsonPath('data.provider_count', 4)
            ->assertJsonCount(4, 'data.providers')
            ->assertJsonPath('data.providers.0.market', 'FOREX')
            ->assertJsonPath('data.providers.0.status', 'HEALTHY')
            ->assertJsonPath(
                'data.providers.0.cache.fresh_datasets_cached',
                1
            )
            ->assertJsonPath(
                'data.providers.0.cache.fallback_datasets_cached',
                1
            )
            ->assertJsonPath('data.providers.1.market', 'CRYPTO')
            ->assertJsonPath('data.providers.2.market', 'DERIV')
            ->assertJsonPath('data.providers.3.market', 'STOCKS')
            ->assertJsonPath(
                'data.providers.3.external_display_approved',
                true
            );

        $body = $response->getContent();

        $this->assertStringNotContainsString(
            'health-test-forex-secret',
            $body
        );
        $this->assertStringNotContainsString(
            'health-test-stocks-secret',
            $body
        );

        Http::assertNothingSent();
    }

    public function test_unapproved_stock_display_rights_are_reported_as_unconfigured(): void
    {
        config()->set(
            'tradex_screener_stocks.provider.external_display_approved',
            false
        );

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson('/api/v1/screener/health')
            ->assertOk()
            ->assertJsonPath('data.overall_status', 'UNCONFIGURED')
            ->assertJsonPath('data.providers.3.market', 'STOCKS')
            ->assertJsonPath('data.providers.3.status', 'UNCONFIGURED')
            ->assertJsonPath('data.providers.3.configured', false)
            ->assertJsonPath(
                'data.providers.3.external_display_approved',
                false
            );

        Http::assertNothingSent();
    }

    private function markProvidersHealthy(): void
    {
        $now = now('UTC')->toIso8601String();

        foreach ([
            'tradex:screener:provider:twelve-data-forex:health',
            'tradex:screener:provider:binance-crypto:health',
            'tradex:screener:provider:deriv:health',
            'tradex:screener:provider:twelve-data-stocks:health',
        ] as $key) {
            Cache::put(
                $key,
                [
                    'status' => 'HEALTHY',
                    'last_success_at_utc' => $now,
                    'last_failure_at_utc' => null,
                    'last_error_code' => null,
                    'last_latency_ms' => 12,
                ],
                3600
            );
        }
    }
}
