<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicWebsiteTest extends TestCase
{
    public function test_homepage_embeds_the_live_tradingview_chart(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('embed-widget-advanced-chart.js', false)
            ->assertSee('BINANCE:BTCUSDT', false)
            ->assertSee('"studies": []', false);
    }

    public function test_public_information_pages_are_available(): void
    {
        foreach ([
            '/about',
            '/platform',
            '/education',
            '/market-intelligence',
            '/tools',
            '/faq',
            '/terms',
            '/privacy',
            '/risk-disclosure',
        ] as $path) {
            $this->get($path)->assertOk()->assertSee('TRADE');
        }
    }

    public function test_contact_page_shows_confirmed_company_details(): void
    {
        $this->get('/contact')
            ->assertOk()
            ->assertSee('Billionaire Trading Institute')
            ->assertSee('PV00296953')
            ->assertSee('+94 72 254 2311');
    }

    public function test_sitemap_is_available_as_xml(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee('market-intelligence', false);
    }
}
