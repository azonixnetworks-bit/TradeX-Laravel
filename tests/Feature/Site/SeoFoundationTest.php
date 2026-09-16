<?php

namespace Tests\Feature\Site;

use Tests\TestCase;

class SeoFoundationTest extends TestCase
{
    public function test_public_homepage_is_available_and_contains_core_seo_metadata(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('TradeX Trading Platform', false);
        $response->assertSee('rel="canonical"', false);
        $response->assertSee('application/ld+json', false);
        $response->assertSee('The OS for Every Trader');
    }

    public function test_sitemap_contains_indexable_public_pages(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('/features');
        $response->assertSee('/trading-tools');
        $response->assertSee('/economic-calendar');
        $response->assertSee('/market-news');
    }

    public function test_non_production_robots_blocks_indexing(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertSee("User-agent: *\nDisallow: /", false);
    }

    public function test_private_surfaces_receive_noindex_header(): void
    {
        $response = $this->get('/admin/login');

        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_llms_summary_is_available(): void
    {
        $response = $this->get('/llms.txt');

        $response->assertOk();
        $response->assertSee('TradeX — The OS for Every Trader.');
        $response->assertSee('/sitemap.xml');
    }
}
