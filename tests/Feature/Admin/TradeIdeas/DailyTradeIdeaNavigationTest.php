<?php

namespace Tests\Feature\Admin\TradeIdeas;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DailyTradeIdeaNavigationTest extends TestCase
{
    public function test_sidebar_exposes_long_term_and_daily_trade_idea_hierarchy(): void
    {
        $sidebar = File::get(
            resource_path('views/admin/layouts/sidebar.blade.php')
        );

        $this->assertStringContainsString('TRADE IDEAS', $sidebar);
        $this->assertStringContainsString('Long-Term Trade Ideas', $sidebar);
        $this->assertStringContainsString('Daily Trade Ideas', $sidebar);
        $this->assertStringContainsString("admin.trade_ideas.index", $sidebar);
        $this->assertStringContainsString("admin.daily_trade_ideas.index", $sidebar);
    }

    public function test_second_brain_navigation_is_separate_from_trade_ideas(): void
    {
        $sidebar = File::get(
            resource_path('views/admin/layouts/sidebar.blade.php')
        );

        $tradeIdeas = strpos($sidebar, '<div class="nav-section-title">TRADE IDEAS</div>');
        $secondBrain = strpos($sidebar, '<div class="nav-section-title">2ND BRAIN</div>');
        $copyTrading = strpos($sidebar, '<div class="nav-section-title">COPY TRADING</div>');

        $this->assertNotFalse($tradeIdeas);
        $this->assertNotFalse($secondBrain);
        $this->assertNotFalse($copyTrading);
        $this->assertLessThan($secondBrain, $tradeIdeas);
        $this->assertLessThan($copyTrading, $secondBrain);

        $tradeIdeasMarkup = substr($sidebar, $tradeIdeas, $secondBrain - $tradeIdeas);
        $secondBrainMarkup = substr($sidebar, $secondBrain, $copyTrading - $secondBrain);

        $this->assertStringContainsString('Long-Term Trade Ideas', $tradeIdeasMarkup);
        $this->assertStringContainsString('Daily Trade Ideas', $tradeIdeasMarkup);
        $this->assertStringNotContainsString('2ND Brain Forex', $tradeIdeasMarkup);
        $this->assertStringContainsString('2ND Brain Forex', $secondBrainMarkup);
        $this->assertStringContainsString('2ND Brain Crypto', $secondBrainMarkup);
        $this->assertStringContainsString('2ND Brain Deriv', $secondBrainMarkup);
        $this->assertStringContainsString('Knowledge Center', $secondBrainMarkup);
    }

    public function test_daily_trade_idea_lifecycle_routes_are_registered(): void
    {
        foreach ([
            'admin.daily_trade_ideas.index',
            'admin.daily_trade_ideas.create',
            'admin.daily_trade_ideas.store',
            'admin.daily_trade_ideas.show',
            'admin.daily_trade_ideas.edit',
            'admin.daily_trade_ideas.update',
            'admin.daily_trade_ideas.publish',
            'admin.daily_trade_ideas.archive',
            'admin.daily_trade_ideas.chart_image',
        ] as $name) {
            $this->assertTrue(Route::has($name), $name.' route is missing.');
        }
    }

    public function test_existing_long_term_route_contract_is_preserved(): void
    {
        foreach ([
            'admin.trade_ideas.index',
            'admin.trade_ideas.create',
            'admin.trade_ideas.store',
            'admin.trade_ideas.show',
            'admin.trade_ideas.edit',
            'admin.trade_ideas.update',
            'admin.trade_ideas.publication',
            'admin.trade_ideas.close',
            'admin.trade_ideas.destroy',
        ] as $name) {
            $this->assertTrue(Route::has($name), $name.' route changed unexpectedly.');
        }
    }
}
