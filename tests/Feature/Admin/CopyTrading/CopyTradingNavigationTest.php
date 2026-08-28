<?php

namespace Tests\Feature\Admin\CopyTrading;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CopyTradingNavigationTest extends TestCase
{
    public function test_sidebar_exposes_dedicated_copy_trading_hierarchy(): void
    {
        $sidebar = File::get(
            resource_path('views/admin/layouts/sidebar.blade.php')
        );

        $this->assertStringContainsString('COPY TRADING', $sidebar);
        $this->assertStringContainsString('Broker Management', $sidebar);
        $this->assertStringContainsString('Broker Account Verification', $sidebar);
        $this->assertStringContainsString('Copy Trade Accounts', $sidebar);
        $this->assertStringContainsString('admin.copy_trading.brokers.index', $sidebar);
        $this->assertStringContainsString('admin.copy_trading.verifications.index', $sidebar);
        $this->assertStringContainsString('admin.copy_trading.accounts.index', $sidebar);
    }

    public function test_copy_trading_admin_routes_are_registered(): void
    {
        foreach ([
            'admin.copy_trading.brokers.index',
            'admin.copy_trading.brokers.create',
            'admin.copy_trading.brokers.store',
            'admin.copy_trading.brokers.logo',
            'admin.copy_trading.brokers.edit',
            'admin.copy_trading.brokers.update',
            'admin.copy_trading.brokers.markets.store',
            'admin.copy_trading.brokers.markets.update',
            'admin.copy_trading.verifications.index',
            'admin.copy_trading.verifications.show',
            'admin.copy_trading.verifications.verify',
            'admin.copy_trading.verifications.reject',
            'admin.copy_trading.verifications.request-resubmission',
            'admin.copy_trading.verifications.escalate',
            'admin.copy_trading.verifications.reviews.evidence',
            'admin.copy_trading.accounts.index',
            'admin.copy_trading.accounts.show',
        ] as $name) {
            $this->assertTrue(Route::has($name), $name.' route is missing.');
        }
    }

    public function test_copy_trade_customer_api_routes_are_registered(): void
    {
        foreach ([
            'api.v1.copy-trade.bootstrap',
            'api.v1.copy-trade.brokers.index',
            'api.v1.copy-trade.brokers.logo',
            'api.v1.copy-trade.broker-markets.open-account-action',
            'api.v1.copy-trade.broker-accounts.store',
            'api.v1.copy-trade.broker-accounts.resubmit',
            'api.v1.profile.broker-accounts.index',
        ] as $name) {
            $this->assertTrue(Route::has($name), $name.' route is missing.');
        }
    }

    public function test_broker_list_presenter_does_not_expose_partner_url_directly(): void
    {
        $presenter = File::get(
            app_path('Services/CopyTrading/CopyTradePresenter.php')
        );
        $action = File::get(
            app_path('Http/Controllers/Api/CopyTrading/CopyTradeOpenAccountActionController.php')
        );

        $this->assertStringNotContainsString("'referral_url' =>", $presenter);
        $this->assertStringNotContainsString('open_account_via_tradex_url', $presenter);
        $this->assertStringContainsString('open_account_action_required', $presenter);
        $this->assertStringContainsString('open_account_via_tradex_url', $action);
    }
}
