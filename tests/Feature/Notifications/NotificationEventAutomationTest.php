<?php

namespace Tests\Feature\Notifications;

use App\Models\DailyTradeIdea;
use App\Models\NotificationAutomationEvent;
use App\Models\NotificationMessage;
use App\Models\TradeIdea;
use App\Models\TradeIdeaUpdate;
use App\Services\Notifications\Automation\NotificationAutomationProcessor;
use App\Services\Notifications\Automation\NotificationAutomationPublisher;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class NotificationEventAutomationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTradeXMobileSession;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('tradex_notification_automation.enabled', true);
        Config::set('tradex_notification_automation.immediate_processing', false);
        Config::set('tradex_notification_automation.immediate_push_fanout', false);
        Config::set('tradex_notification_automation.events.trade_ideas', true);
        Config::set('tradex_notification_automation.events.referrals', true);
        Config::set('tradex_notification_automation.events.market_sessions', true);
        Config::set('tradex_push.enabled', false);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_publisher_is_idempotent_and_premium_audience_is_enforced(): void
    {
        $premium = $this->createApiUser([
            'account_type' => 'PREMIUM',
        ]);

        $free = $this->createApiUser([
            'account_type' => 'FREE',
        ]);

        $publisher = app(NotificationAutomationPublisher::class);

        $payload = [
            'audience' => NotificationMessage::AUDIENCE_PREMIUM,
            'category' => NotificationMessage::CATEGORY_SYSTEM,
            'priority' => NotificationMessage::PRIORITY_NORMAL,
            'title' => 'Automation Test',
            'body' => 'One semantic event must create exactly one notification.',
            'action_type' => NotificationMessage::ACTION_DASHBOARD,
            'action_resource_uuid' => null,
        ];

        $publisher->publish(
            eventKey: 'test:idempotent:one',
            eventType: 'TEST_EVENT',
            payload: $payload,
            sourceType: 'TEST',
            sourceReference: 'one'
        );

        $publisher->publish(
            eventKey: 'test:idempotent:one',
            eventType: 'TEST_EVENT',
            payload: $payload,
            sourceType: 'TEST',
            sourceReference: 'one'
        );

        $this->assertDatabaseCount('notification_automation_events', 1);

        $result = app(NotificationAutomationProcessor::class)->sweep();

        $this->assertSame(1, $result['processed']);
        $this->assertSame(0, $result['failed']);
        $this->assertDatabaseCount('notification_messages', 1);

        $message = NotificationMessage::query()->firstOrFail();

        $this->assertDatabaseHas('user_notification_receipts', [
            'notification_message_id' => $message->id,
            'user_id' => $premium->id,
        ]);

        $this->assertDatabaseMissing('user_notification_receipts', [
            'notification_message_id' => $message->id,
            'user_id' => $free->id,
        ]);

        $this->assertDatabaseHas('notification_automation_events', [
            'event_type' => 'TEST_EVENT',
            'status' => NotificationAutomationEvent::STATUS_PROCESSED,
            'notification_message_id' => $message->id,
            'recipient_count' => 1,
        ]);
    }

    public function test_new_referral_notifies_only_the_direct_referrer(): void
    {
        $referrer = $this->createApiUser();
        $unrelated = $this->createApiUser();

        $referred = $this->createApiUser([
            'referred_by_user_id' => $referrer->id,
        ]);

        $this->assertDatabaseHas('notification_automation_events', [
            'event_type' => 'AFFILIATE_REFERRAL_JOINED',
            'source_type' => 'USER',
            'source_reference' => $referred->uuid,
            'status' => NotificationAutomationEvent::STATUS_PENDING,
        ]);

        app(NotificationAutomationProcessor::class)->sweep();

        $event = NotificationAutomationEvent::query()
            ->where('event_type', 'AFFILIATE_REFERRAL_JOINED')
            ->firstOrFail();

        $this->assertNotNull($event->notification_message_id);
        $this->assertSame(1, $event->recipient_count);

        $this->assertDatabaseHas('user_notification_receipts', [
            'notification_message_id' => $event->notification_message_id,
            'user_id' => $referrer->id,
        ]);

        $this->assertDatabaseMissing('user_notification_receipts', [
            'notification_message_id' => $event->notification_message_id,
            'user_id' => $unrelated->id,
        ]);

        $this->assertDatabaseMissing('user_notification_receipts', [
            'notification_message_id' => $event->notification_message_id,
            'user_id' => $referred->id,
        ]);
    }

    public function test_long_term_trade_idea_publish_chart_and_live_updates_notify_premium_users(): void
    {
        $premium = $this->createApiUser([
            'account_type' => 'PREMIUM',
        ]);

        $free = $this->createApiUser([
            'account_type' => 'FREE',
        ]);

        $idea = $this->createTradeIdeaDraft();

        $idea->forceFill([
            'status' => TradeIdea::STATUS_PUBLISHED,
            'published_at' => now(),
        ])->save();

        $idea->forceFill([
            'chart_image_disk' => 'private',
            'chart_image_path' => 'trade-ideas/test/chart-v2.png',
            'chart_image_mime_type' => 'image/png',
            'chart_image_size_bytes' => 1234,
        ])->save();

        TradeIdeaUpdate::query()->create([
            'trade_idea_id' => $idea->id,
            'trade_idea_target_id' => null,
            'type' => TradeIdeaUpdate::TYPE_STOP_MOVED,
            'message' => 'Protective stop adjusted.',
            'price' => '1.10100',
            'created_by_admin_id' => null,
        ]);

        $this->assertDatabaseHas('notification_automation_events', [
            'event_type' => 'TRADE_IDEA_PUBLISHED',
            'source_reference' => $idea->uuid,
        ]);

        $this->assertDatabaseHas('notification_automation_events', [
            'event_type' => 'TRADE_IDEA_CHART_UPDATED',
            'source_reference' => $idea->uuid,
        ]);

        $this->assertDatabaseHas('notification_automation_events', [
            'event_type' => 'TRADE_IDEA_UPDATE_ADDED',
            'source_type' => 'TRADE_IDEA_UPDATE',
        ]);

        $result = app(NotificationAutomationProcessor::class)->sweep();

        $this->assertSame(3, $result['processed']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame(3, NotificationMessage::query()->count());

        $this->assertSame(
            3,
            (int) DB::table('user_notification_receipts')
                ->where('user_id', $premium->id)
                ->count()
        );

        $this->assertSame(
            0,
            (int) DB::table('user_notification_receipts')
                ->where('user_id', $free->id)
                ->count()
        );
    }

    public function test_day_trade_publish_and_chart_update_notify_premium_users(): void
    {
        $premium = $this->createApiUser([
            'account_type' => 'PREMIUM',
        ]);

        $free = $this->createApiUser([
            'account_type' => 'FREE',
        ]);

        $idea = $this->createDailyTradeIdeaDraft();

        $idea->forceFill([
            'status' => DailyTradeIdea::STATUS_PUBLISHED,
            'published_at' => now(),
        ])->save();

        $idea->forceFill([
            'tradingview_url' =>
                'https://www.tradingview.com/chart/TestDayTrade/',
        ])->save();

        $result = app(NotificationAutomationProcessor::class)->sweep();

        $this->assertSame(2, $result['processed']);
        $this->assertSame(0, $result['failed']);

        $this->assertDatabaseHas('notification_automation_events', [
            'event_type' => 'DAILY_TRADE_IDEA_PUBLISHED',
            'source_reference' => $idea->uuid,
        ]);

        $this->assertDatabaseHas('notification_automation_events', [
            'event_type' => 'DAILY_TRADE_IDEA_CHART_UPDATED',
            'source_reference' => $idea->uuid,
        ]);

        $this->assertSame(
            2,
            (int) DB::table('user_notification_receipts')
                ->where('user_id', $premium->id)
                ->count()
        );

        $this->assertSame(
            0,
            (int) DB::table('user_notification_receipts')
                ->where('user_id', $free->id)
                ->count()
        );
    }

    public function test_market_session_reminders_follow_iana_dst_and_are_idempotent(): void
    {
        Config::set(
            'tradex_notification_automation.market_sessions.sessions',
            [
                'london' => [
                    'enabled' => true,
                    'label' => 'London Session',
                    'market_clock_key' => 'london',
                ],
                'new_york_pm' => [
                    'enabled' => false,
                    'label' => 'New York PM Session',
                    'timezone' => 'America/New_York',
                    'time' => null,
                    'days' => [1, 2, 3, 4, 5],
                ],
            ]
        );

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse('2026-01-05 08:01:00', 'UTC')
        );

        $this->artisan('tradex:notifications:market-sessions')
            ->assertSuccessful();

        $this->artisan('tradex:notifications:market-sessions')
            ->assertSuccessful();

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse('2026-07-06 07:01:00', 'UTC')
        );

        $this->artisan('tradex:notifications:market-sessions')
            ->assertSuccessful();

        $this->assertSame(
            2,
            NotificationAutomationEvent::query()
                ->where('event_type', 'MARKET_SESSION_STARTED')
                ->where('source_reference', 'london')
                ->count()
        );

        $this->assertDatabaseMissing('notification_automation_events', [
            'event_type' => 'MARKET_SESSION_STARTED',
            'source_reference' => 'new_york_pm',
        ]);
    }

    public function test_market_session_reminder_does_not_fire_on_weekend(): void
    {
        Config::set(
            'tradex_notification_automation.market_sessions.sessions',
            [
                'asia' => [
                    'enabled' => true,
                    'label' => 'Asia Session',
                    'market_clock_key' => 'tokyo',
                ],
            ]
        );

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse('2026-09-06 00:01:00', 'UTC')
        );

        $this->artisan('tradex:notifications:market-sessions')
            ->assertSuccessful();

        $this->assertDatabaseCount('notification_automation_events', 0);
    }

    public function test_console_scheduler_wires_market_event_processing_and_push_sweeps(): void
    {
        $source = file_get_contents(base_path('routes/console.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString(
            "tradex:notifications:market-sessions",
            $source
        );
        $this->assertStringContainsString(
            "tradex:notifications:sweep",
            $source
        );
        $this->assertStringContainsString(
            "tradex:push:sweep",
            $source
        );
        $this->assertStringContainsString('->everyMinute()', $source);
    }

    private function createTradeIdeaDraft(): TradeIdea
    {
        return TradeIdea::query()->create([
            'title' => 'EURUSD Long-Term Idea',
            'summary' => null,
            'analysis' => null,
            'tradingview_url' => null,
            'chart_image_disk' => null,
            'chart_image_path' => null,
            'chart_image_mime_type' => null,
            'chart_image_size_bytes' => null,
            'category' => TradeIdea::CATEGORY_FOREX,
            'market_region' => null,
            'symbol' => 'EURUSD',
            'instrument_name' => 'Euro / US Dollar',
            'direction' => TradeIdea::DIRECTION_BUY,
            'timeframe' => 'H4',
            'entry_price_low' => null,
            'entry_price_high' => null,
            'stop_loss' => null,
            'current_stop_loss' => null,
            'entry_triggered_at' => null,
            'entry_price_actual' => null,
            'status' => TradeIdea::STATUS_DRAFT,
            'published_at' => null,
            'closed_at' => null,
            'close_outcome' => null,
            'close_price' => null,
            'close_note' => null,
            'created_by_admin_id' => null,
            'updated_by_admin_id' => null,
        ]);
    }

    private function createDailyTradeIdeaDraft(): DailyTradeIdea
    {
        return DailyTradeIdea::query()->create([
            'title' => 'GBPUSD Day Trade Idea',
            'summary' => null,
            'analysis' => null,
            'tradingview_url' => null,
            'chart_image_disk' => null,
            'chart_image_path' => null,
            'chart_image_mime_type' => null,
            'chart_image_size_bytes' => null,
            'category' => 'FOREX',
            'market_region' => null,
            'symbol' => 'GBPUSD',
            'instrument_name' => 'British Pound / US Dollar',
            'direction' => 'SELL',
            'timeframe' => 'M15',
            'entry_price_low' => null,
            'entry_price_high' => null,
            'stop_loss' => null,
            'status' => DailyTradeIdea::STATUS_DRAFT,
            'published_at' => null,
            'archived_at' => null,
            'created_by_admin_id' => null,
            'updated_by_admin_id' => null,
        ]);
    }
}
