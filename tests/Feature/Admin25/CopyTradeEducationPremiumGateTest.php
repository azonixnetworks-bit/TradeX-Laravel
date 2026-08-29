<?php

namespace Tests\Feature\Admin25;

use App\Exceptions\AuthApiException;
use App\Http\Middleware\EnsureEducationPremium;
use App\Models\CopyTradeBroker;
use App\Models\CopyTradeBrokerMarket;
use App\Models\Product;
use App\Models\UserEntitlement;
use App\Services\CopyTrading\CopyTradePresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class CopyTradeEducationPremiumGateTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_free_user_bootstrap_state_requires_education_premium(): void
    {
        $user = $this->createApiUser([
            'account_type' => 'FREE',
        ]);

        $data = app(
            CopyTradePresenter::class
        )->bootstrap(
            $user,
            new Collection(),
            new Collection()
        );

        $this->assertSame(
            'EDUCATION_PREMIUM_REQUIRED',
            $data['next_step']
        );
        $this->assertFalse(
            $data['education_premium']['active']
        );
        $this->assertFalse(
            $data['copy_trade_ready']
        );
    }


    public function test_copy_trade_open_account_http_action_is_premium_gated(): void
    {
        $broker =
            CopyTradeBroker::query()
                ->create([
                    'code' =>
                        'ADMIN25_TEST_BROKER',
                    'name' =>
                        'ADMIN-25 Test Broker',
                    'is_active' =>
                        true,
                ]);

        $market =
            CopyTradeBrokerMarket::query()
                ->create([
                    'broker_id' =>
                        $broker->getKey(),
                    'market_category' =>
                        'FOREX',
                    'referral_url' =>
                        'https://broker.example.test/tradex-admin25',
                    'required_currency' =>
                        'USD',
                    'live_account_only' =>
                        true,
                    'is_active' =>
                        true,
                ]);

        $user =
            $this->createApiUser([
                'account_type' =>
                    'FREE',
            ]);

        $token =
            $this->issueBoundToken(
                $user
            );

        $this->withToken($token)
            ->postJson(
                route(
                    'api.v1.copy-trade.broker-markets.open-account-action',
                    [
                        'copyTradeBrokerMarket' =>
                            $market,
                    ]
                )
            )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'EDUCATION_PREMIUM_REQUIRED'
            );

        UserEntitlement::query()
            ->create([
                'user_id' =>
                    $user->getKey(),
                'product_code' =>
                    Product::CODE_EDUCATION_PREMIUM,
                'status' =>
                    UserEntitlement::STATUS_ACTIVE,
                'activated_at' =>
                    now(),
                'payment_id' =>
                    null,
            ]);

        $this->withToken($token)
            ->postJson(
                route(
                    'api.v1.copy-trade.broker-markets.open-account-action',
                    [
                        'copyTradeBrokerMarket' =>
                            $market,
                    ]
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            );
    }

    public function test_copy_trade_write_middleware_blocks_free_user_and_allows_active_entitlement(): void
    {
        $user = $this->createApiUser([
            'account_type' => 'FREE',
        ]);

        $request = Request::create(
            '/api/v1/copy-trade/broker-accounts',
            'POST'
        );
        $request->setUserResolver(
            fn () => $user
        );

        $middleware = app(
            EnsureEducationPremium::class
        );

        try {
            $middleware->handle(
                $request,
                fn () => response('ok')
            );

            $this->fail(
                'FREE user was not blocked.'
            );
        } catch (AuthApiException $exception) {
            $this->assertSame(
                'EDUCATION_PREMIUM_REQUIRED',
                $exception->apiCode
            );
        }

        UserEntitlement::query()
            ->create([
                'user_id' =>
                    $user->getKey(),
                'product_code' =>
                    Product::CODE_EDUCATION_PREMIUM,
                'status' =>
                    UserEntitlement::STATUS_ACTIVE,
                'activated_at' =>
                    now(),
                'payment_id' =>
                    null,
            ]);

        $response =
            $middleware->handle(
                $request,
                fn () => response(
                    'ok',
                    200
                )
            );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );
    }
}
