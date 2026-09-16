<?php

namespace Tests\Feature\Api\EconomicNews;

use App\Models\AdminUser;
use App\Models\EconomicEvent;
use App\Models\EconomicEventOverride;
use App\Models\EconomicEventPair;
use App\Models\EconomicEventUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class EconomicNewsApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTradeXMobileSession;

    public function test_economic_news_requires_authenticated_bound_mobile_session(): void
    {
        $this->getJson('/api/v1/economic-news/events')
            ->assertUnauthorized();
    }

    public function test_event_feed_exposes_only_published_events(): void
    {
        $published = $this->event([
            'title' => 'US CPI',
            'impact' => EconomicEvent::IMPACT_HIGH,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->event([
            'title' => 'Internal Draft Event',
            'impact' => EconomicEvent::IMPACT_MEDIUM,
            'is_published' => false,
            'published_at' => null,
        ]);

        $token = $this->issueBoundToken($this->createApiUser());

        $this->withToken($token)
            ->getJson('/api/v1/economic-news/events')
            ->assertOk()
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.uuid', $published->uuid)
            ->assertJsonPath('data.events.0.title', 'US CPI');
    }

    public function test_high_impact_feed_uses_effective_override_and_safe_pair_intelligence(): void
    {
        $event = $this->event([
            'title' => 'FOMC Decision',
            'impact' => EconomicEvent::IMPACT_MEDIUM,
            'bias' => EconomicEvent::BIAS_NEUTRAL,
            'confidence_score' => 35,
            'volatility' => EconomicEvent::VOLATILITY_MEDIUM,
            'reason' => 'Source baseline reason.',
            'analysis' => 'Source baseline analysis.',
            'is_published' => true,
            'published_at' => now(),
        ]);

        EconomicEventPair::query()->create([
            'economic_event_id' => $event->id,
            'market' => 'FOREX',
            'symbol' => 'EURUSD',
            'bias' => EconomicEvent::BIAS_BEARISH,
            'confidence_score' => 82,
            'volatility' => EconomicEvent::VOLATILITY_HIGH,
            'reason' => 'USD strength implies EURUSD downside pressure.',
            'mapping_source' => 'MANUAL',
            'is_primary' => true,
            'sort_order' => 10,
        ]);

        $admin = $this->admin();

        EconomicEventOverride::query()->create([
            'economic_event_id' => $event->id,
            'impact' => EconomicEvent::IMPACT_HIGH,
            'bias' => EconomicEvent::BIAS_BULLISH,
            'confidence_score' => 91,
            'volatility' => EconomicEvent::VOLATILITY_EXTREME,
            'reason' => 'Effective override reason.',
            'analysis' => 'Effective override analysis.',
            'override_note' => 'Sensitive admin-only override justification.',
            'is_active' => true,
            'starts_at' => now()->subMinute(),
            'overridden_by_admin_id' => $admin->id,
        ]);

        $token = $this->issueBoundToken($this->createApiUser());

        $response = $this->withToken($token)
            ->getJson('/api/v1/economic-news/high-impact')
            ->assertOk()
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.uuid', $event->uuid)
            ->assertJsonPath('data.events.0.impact', 'HIGH')
            ->assertJsonPath('data.events.0.bias', 'BULLISH')
            ->assertJsonPath('data.events.0.confidence_score', 91)
            ->assertJsonPath('data.events.0.volatility', 'EXTREME')
            ->assertJsonPath('data.events.0.intelligence_provenance.manual_override', true)
            ->assertJsonPath('data.events.0.intelligence_provenance.mode', 'ADMIN_OVERRIDE')
            ->assertJsonPath('data.events.0.affected_pairs.0.symbol', 'EURUSD')
            ->assertJsonPath('data.events.0.affected_pairs.0.bias', 'BEARISH');

        $response->assertDontSee('Sensitive admin-only override justification.', false);
        $response->assertDontSee('overridden_by_admin_id', false);
        $response->assertDontSee('source_payload_json', false);
    }

    public function test_event_detail_exposes_effective_analysis_but_not_admin_override_note(): void
    {
        $event = $this->event([
            'title' => 'NFP',
            'is_published' => true,
            'published_at' => now(),
        ]);
        $admin = $this->admin();

        EconomicEventOverride::query()->create([
            'economic_event_id' => $event->id,
            'analysis' => 'Customer-safe effective analysis.',
            'override_note' => 'Internal audit note must remain private.',
            'is_active' => true,
            'starts_at' => now()->subMinute(),
            'overridden_by_admin_id' => $admin->id,
        ]);

        $token = $this->issueBoundToken($this->createApiUser());

        $response = $this->withToken($token)
            ->getJson('/api/v1/economic-news/events/'.$event->uuid)
            ->assertOk()
            ->assertJsonPath('data.event.analysis', 'Customer-safe effective analysis.');

        $response->assertDontSee('Internal audit note must remain private.', false);
    }

    public function test_live_feed_requires_published_update_and_published_parent_event(): void
    {
        $publicEvent = $this->event([
            'title' => 'Public Release',
            'is_published' => true,
            'published_at' => now(),
        ]);
        $privateEvent = $this->event([
            'title' => 'Private Release',
            'is_published' => false,
            'published_at' => null,
        ]);

        $publicUpdate = $this->update($publicEvent, true, 'Published update');
        $this->update($publicEvent, false, 'Draft update');
        $this->update($privateEvent, true, 'Update on unpublished event');

        $token = $this->issueBoundToken($this->createApiUser());

        $this->withToken($token)
            ->getJson('/api/v1/economic-news/live')
            ->assertOk()
            ->assertJsonCount(1, 'data.live_updates')
            ->assertJsonPath('data.live_updates.0.uuid', $publicUpdate->uuid)
            ->assertJsonPath('data.live_updates.0.message', 'Published update');
    }

    private function event(array $overrides = []): EconomicEvent
    {
        return EconomicEvent::query()->create(array_merge([
            'origin' => 'MANUAL',
            'title' => 'Economic Event',
            'country_code' => 'US',
            'currency' => 'USD',
            'impact' => EconomicEvent::IMPACT_MEDIUM,
            'scheduled_at_utc' => now()->addHour(),
            'source_timezone' => 'UTC',
            'status' => EconomicEvent::STATUS_SCHEDULED,
            'bias' => EconomicEvent::BIAS_NEUTRAL,
            'confidence_score' => 50,
            'volatility' => EconomicEvent::VOLATILITY_MEDIUM,
            'is_published' => true,
            'published_at' => now(),
        ], $overrides));
    }

    private function update(EconomicEvent $event, bool $published, string $message): EconomicEventUpdate
    {
        return EconomicEventUpdate::query()->create([
            'economic_event_id' => $event->id,
            'update_type' => EconomicEventUpdate::TYPE_LIVE,
            'message' => $message,
            'is_published' => $published,
            'published_at' => now(),
        ]);
    }

    private function admin(): AdminUser
    {
        return AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Economic News API Admin',
            'email' => 'economic-news-api-'.Str::lower(Str::random(10)).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);
    }
}
