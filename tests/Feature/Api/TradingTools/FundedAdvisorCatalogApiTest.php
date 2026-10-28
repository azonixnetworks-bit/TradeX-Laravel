<?php

namespace Tests\Feature\Api\TradingTools;

use App\Models\FundedAccountPartner;
use App\Models\FundedNoTradeRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class FundedAdvisorCatalogApiTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_catalog_and_version_require_an_authenticated_mobile_session(): void
    {
        $this->getJson('/api/v1/funded-account-advisor/version')->assertUnauthorized();
        $this->getJson('/api/v1/funded-account-advisor/catalog')->assertUnauthorized();
    }

    public function test_catalog_returns_only_public_active_and_effective_content(): void
    {
        $token = $this->issueBoundToken($this->createApiUser());
        $secondary = $this->partner(['name' => 'Secondary', 'priority' => 1]);
        $featured = $this->partner([
            'name' => 'Featured',
            'is_featured' => true,
            'priority' => 99,
            'logo_disk' => 'local',
            'logo_path' => 'private/funded/featured.png',
            'logo_mime_type' => 'image/png',
            'logo_size_bytes' => 123,
        ]);
        $this->partner(['name' => 'Inactive', 'is_active' => false]);

        $effective = $this->rule($featured, ['program_name' => 'Two Step']);
        $this->rule($featured, ['program_name' => 'Inactive', 'is_active' => false]);
        $this->rule($featured, ['program_name' => 'Future', 'effective_from' => now()->addDay()]);
        $this->rule($featured, ['program_name' => 'Expired', 'expires_at' => now()->subMinute()]);
        $deleted = $this->rule($featured, ['program_name' => 'Deleted']);
        $deleted->delete();
        $this->rule($secondary, ['program_name' => 'Secondary Plan']);

        $response = $this->withToken($token)
            ->getJson('/api/v1/funded-account-advisor/catalog')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.partners.0.name', 'Featured')
            ->assertJsonPath('data.partners.1.name', 'Secondary')
            ->assertJsonCount(2, 'data.partners')
            ->assertJsonCount(1, 'data.partners.0.rules')
            ->assertJsonPath('data.partners.0.rules.0.uuid', $effective->uuid)
            ->assertJsonMissingPath('data.partners.0.id')
            ->assertJsonMissingPath('data.partners.0.logo_disk')
            ->assertJsonMissingPath('data.partners.0.logo_path')
            ->assertJsonMissingPath('data.partners.0.created_by_admin_id');

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $response->json('data.version'));
        $this->assertStringContainsString(
            '/api/v1/funded-account-advisor/partners/'.$featured->uuid.'/logo',
            (string) $response->json('data.partners.0.logo_url')
        );
    }

    public function test_version_is_stable_then_changes_when_catalog_content_changes(): void
    {
        $token = $this->issueBoundToken($this->createApiUser());
        $partner = $this->partner();
        $this->rule($partner);

        $first = (string) $this->withToken($token)
            ->getJson('/api/v1/funded-account-advisor/version')
            ->assertOk()
            ->json('data.version');
        $second = (string) $this->withToken($token)
            ->getJson('/api/v1/funded-account-advisor/version')
            ->assertOk()
            ->json('data.version');
        $this->assertSame($first, $second);

        $partner->update(['offer_text' => 'A newly verified offer']);
        $changed = (string) $this->withToken($token)
            ->getJson('/api/v1/funded-account-advisor/version')
            ->assertOk()
            ->json('data.version');
        $this->assertNotSame($first, $changed);
    }

    public function test_matching_etag_returns_not_modified(): void
    {
        $token = $this->issueBoundToken($this->createApiUser());
        $this->partner();

        $response = $this->withToken($token)
            ->getJson('/api/v1/funded-account-advisor/catalog')
            ->assertOk();
        $etag = (string) $response->headers->get('ETag');

        $this->withToken($token)
            ->withHeader('If-None-Match', $etag)
            ->get('/api/v1/funded-account-advisor/catalog')
            ->assertStatus(304);
    }

    /** @param array<string, mixed> $overrides */
    private function partner(array $overrides = []): FundedAccountPartner
    {
        return FundedAccountPartner::query()->create(array_merge([
            'name' => 'TradeX Funding Partner',
            'website_url' => 'https://partner.example',
            'affiliate_url' => 'https://partner.example/tradex',
            'promo_code' => 'TRADEX10',
            'offer_text' => 'Verified TradeX offer',
            'disclosure' => 'Rules may change. Verify the official source before trading.',
            'supported_countries' => ['LK', 'IN'],
            'account_sizes' => [10000, 25000],
            'challenge_types' => ['Two Step'],
            'is_featured' => false,
            'is_active' => true,
            'priority' => 10,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function rule(
        FundedAccountPartner $partner,
        array $overrides = []
    ): FundedNoTradeRule {
        return FundedNoTradeRule::query()->create(array_merge([
            'funded_account_partner_id' => $partner->id,
            'program_name' => 'Evaluation',
            'phase' => FundedNoTradeRule::PHASE_ALL,
            'policy' => FundedNoTradeRule::POLICY_RESTRICTED,
            'enforcement_type' => FundedNoTradeRule::ENFORCEMENT_BLOCKED,
            'impact_levels' => ['HIGH'],
            'event_keywords' => ['CPI', 'NFP'],
            'minutes_before' => 2,
            'minutes_after' => 2,
            'affected_instruments_only' => true,
            'restricted_currencies' => ['USD'],
            'restricted_instruments' => ['XAUUSD'],
            'block_open_new_trades' => true,
            'block_close_trades' => false,
            'block_pending_orders' => true,
            'rule_summary' => 'Do not open trades around high-impact news.',
            'source_url' => 'https://partner.example/rules',
            'source_reference' => 'News trading policy',
            'verified_at' => now()->subHour(),
            'effective_from' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'is_active' => true,
            'priority' => 10,
        ], $overrides));
    }
}
