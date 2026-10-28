<?php

namespace Tests\Feature\Api\TradingTools;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class RiskRewardRouteSecurityTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.key', 'base64:YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE=');
    }

    public function test_mobile_calculator_routes_are_protected_by_sanctum_and_device_binding(): void
    {
        foreach ([
            'api.v1.risk-reward.catalog',
            'api.v1.risk-reward.calculate',
            'api.v1.risk-reward.dividends.calculate',
            'api.v1.risk-reward.saved.index',
            'api.v1.risk-reward.saved.store',
        ] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth:sanctum', $middleware);
            $this->assertContains('tradex.mobile', $middleware);
        }
    }

    public function test_customer_catalog_rejects_an_unauthenticated_request(): void
    {
        $this->getJson(route('api.v1.risk-reward.catalog'))->assertUnauthorized();
    }

    public function test_saved_calculation_is_recalculated_and_scoped_to_its_customer(): void
    {
        $owner = $this->createApiUser(['email' => 'risk-owner@tradex.test']);
        $other = $this->createApiUser(['email' => 'risk-other@tradex.test']);
        $ownerToken = $this->issueBoundToken($owner);
        $otherToken = $this->issueBoundToken($other);

        $saved = $this->withToken($ownerToken)
            ->postJson(route('api.v1.risk-reward.saved.store'), [
                'calculator_type' => 'risk_reward',
                'title' => 'EURUSD plan',
                'input' => [
                    'market' => 'forex',
                    'symbol' => 'EURUSD',
                    'side' => 'BUY',
                    'entry' => 1.1000,
                    'stop_loss' => 1.0950,
                    'take_profit' => 1.1250,
                    'risk_amount' => 100,
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'EURUSD plan')
            ->assertJsonPath('data.result.symbol', 'EURUSD');

        $this->assertEqualsWithDelta(5.0, (float) $saved->json('data.result.risk_reward_ratio'), 0.000001);

        $uuid = (string) $saved->json('data.uuid');

        $this->withToken($ownerToken)
            ->getJson(route('api.v1.risk-reward.saved.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.uuid', $uuid);

        $this->withToken($otherToken)
            ->getJson(route('api.v1.risk-reward.saved.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data.items');

        $this->withToken($otherToken)
            ->deleteJson(route('api.v1.risk-reward.saved.destroy', ['uuid' => $uuid]))
            ->assertNotFound();

        $this->withToken($ownerToken)
            ->deleteJson(route('api.v1.risk-reward.saved.destroy', ['uuid' => $uuid]))
            ->assertOk();
    }

    public function test_crypto_cfd_never_fabricates_margin_without_a_verified_contract(): void
    {
        $user = $this->createApiUser(['email' => 'crypto-risk@tradex.test']);
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->postJson(route('api.v1.risk-reward.calculate'), [
                'market' => 'crypto',
                'symbol' => 'BTCUSD',
                'side' => 'BUY',
                'entry' => 60000,
                'stop_loss' => 59000,
                'risk_amount' => 100,
            ])
            ->assertOk()
            ->assertJsonPath('data.margin.available', false)
            ->assertJsonPath('data.margin.message', 'Margin unavailable — select broker/contract details with a verified margin specification.');
    }
}
