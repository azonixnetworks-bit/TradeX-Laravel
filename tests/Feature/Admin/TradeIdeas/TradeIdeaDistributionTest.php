<?php

namespace Tests\Feature\Admin\TradeIdeas;

use App\Jobs\GenerateTradeIdeaShareImage;
use App\Jobs\SendTelegramTradeIdea;
use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\TradeIdea;
use App\Models\TradeIdeaDistribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class TradeIdeaDistributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_distribution_requires_distribute_permission(): void
    {
        $admin = $this->adminWithPermissions([
            'trade_ideas.view',
        ]);

        $tradeIdea = $this->publishedTradeIdea(
            $admin
        );

        $this->actingAs(
            $admin,
            'admin'
        )->post(
            route(
                'admin.trade_ideas.distributions.store',
                [
                    'tradeIdea' =>
                        $tradeIdea,
                ]
            )
        )->assertForbidden();
    }

    public function test_draft_trade_idea_cannot_be_distributed(): void
    {
        $admin = $this->adminWithPermissions([
            'trade_ideas.distribute',
        ]);

        $tradeIdea = $this->draftTradeIdea(
            $admin
        );

        $this->actingAs(
            $admin,
            'admin'
        )->post(
            route(
                'admin.trade_ideas.distributions.store',
                [
                    'tradeIdea' =>
                        $tradeIdea,
                ]
            )
        )->assertStatus(
            422
        );
    }

    public function test_manual_distribution_queues_ready_telegram_destination_idempotently(): void
    {
        Queue::fake();

        config([
            'tradex_trade_ideas.telegram.enabled' =>
                true,

            'tradex_trade_ideas.telegram.bot_token' =>
                'test-token',

            'tradex_trade_ideas.telegram.chat_ids' =>
                [
                    '@tradex_test',
                ],

            'tradex_trade_ideas.whatsapp.enabled' =>
                false,
        ]);

        $admin = $this->adminWithPermissions([
            'trade_ideas.view',
            'trade_ideas.distribute',
        ]);

        $tradeIdea = $this->publishedTradeIdea(
            $admin
        );

        $route = route(
            'admin.trade_ideas.distributions.store',
            [
                'tradeIdea' =>
                    $tradeIdea,
            ]
        );

        $this->actingAs(
            $admin,
            'admin'
        )->post(
            $route
        )->assertRedirect(
            route(
                'admin.trade_ideas.show',
                [
                    'tradeIdea' =>
                        $tradeIdea,
                ]
            )
        );

        $distribution =
            TradeIdeaDistribution::query()
                ->firstOrFail();

        $this->assertSame(
            TradeIdeaDistribution::CHANNEL_TELEGRAM,
            $distribution->channel
        );

        $this->assertSame(
            '@tradex_test',
            $distribution->destination
        );

        $this->assertSame(
            TradeIdeaDistribution::STATUS_PENDING,
            $distribution->status
        );

        Queue::assertPushed(
            SendTelegramTradeIdea::class,
            fn (
                SendTelegramTradeIdea $job
            ): bool =>
                $job->distributionId
                === $distribution->id
        );

        Queue::assertPushed(
            GenerateTradeIdeaShareImage::class
        );

        $this->actingAs(
            $admin,
            'admin'
        )->post(
            $route
        )->assertRedirect();

        $this->assertDatabaseCount(
            'trade_idea_distributions',
            1
        );
    }

    public function test_failed_distribution_can_be_retried_but_sent_distribution_cannot(): void
    {
        Queue::fake();

        config([
            'tradex_trade_ideas.telegram.enabled' =>
                true,

            'tradex_trade_ideas.telegram.bot_token' =>
                'test-token',

            'tradex_trade_ideas.telegram.chat_ids' =>
                [
                    '@tradex_test',
                ],

            'tradex_trade_ideas.whatsapp.enabled' =>
                false,
        ]);

        $admin = $this->adminWithPermissions([
            'trade_ideas.view',
            'trade_ideas.distribute',
        ]);

        $tradeIdea = $this->publishedTradeIdea(
            $admin
        );

        $this->actingAs(
            $admin,
            'admin'
        )->post(
            route(
                'admin.trade_ideas.distributions.store',
                [
                    'tradeIdea' =>
                        $tradeIdea,
                ]
            )
        )->assertRedirect();

        $distribution =
            TradeIdeaDistribution::query()
                ->firstOrFail();

        $distribution->forceFill([
            'status' =>
                TradeIdeaDistribution::STATUS_FAILED,

            'failed_at' =>
                now(),

            'last_error_code' =>
                'TEST',

            'last_error_message' =>
                'Safe test failure.',
        ])->save();

        Queue::fake();

        $this->actingAs(
            $admin,
            'admin'
        )->post(
            route(
                'admin.trade_ideas.distributions.retry',
                [
                    'tradeIdea' =>
                        $tradeIdea,

                    'distribution' =>
                        $distribution,
                ]
            )
        )->assertRedirect();

        $distribution->refresh();

        $this->assertSame(
            TradeIdeaDistribution::STATUS_PENDING,
            $distribution->status
        );

        Queue::assertPushed(
            SendTelegramTradeIdea::class
        );

        $distribution->forceFill([
            'status' =>
                TradeIdeaDistribution::STATUS_SENT,

            'sent_at' =>
                now(),
        ])->save();

        Queue::fake();

        $this->actingAs(
            $admin,
            'admin'
        )->post(
            route(
                'admin.trade_ideas.distributions.retry',
                [
                    'tradeIdea' =>
                        $tradeIdea,

                    'distribution' =>
                        $distribution,
                ]
            )
        )->assertRedirect();

        Queue::assertNothingPushed();
    }

    public function test_distribution_retry_is_scoped_to_same_trade_idea(): void
    {
        Queue::fake();

        config([
            'tradex_trade_ideas.telegram.enabled' =>
                true,

            'tradex_trade_ideas.telegram.bot_token' =>
                'test-token',

            'tradex_trade_ideas.telegram.chat_ids' =>
                [
                    '@tradex_test',
                ],

            'tradex_trade_ideas.whatsapp.enabled' =>
                false,
        ]);

        $admin = $this->adminWithPermissions([
            'trade_ideas.distribute',
        ]);

        $ideaA = $this->publishedTradeIdea(
            $admin,
            'EURUSD'
        );

        $ideaB = $this->publishedTradeIdea(
            $admin,
            'GBPUSD'
        );

        $this->actingAs(
            $admin,
            'admin'
        )->post(
            route(
                'admin.trade_ideas.distributions.store',
                [
                    'tradeIdea' =>
                        $ideaA,
                ]
            )
        )->assertRedirect();

        $distribution =
            TradeIdeaDistribution::query()
                ->firstOrFail();

        $distribution->forceFill([
            'status' =>
                TradeIdeaDistribution::STATUS_FAILED,
        ])->save();

        $this->actingAs(
            $admin,
            'admin'
        )->post(
            route(
                'admin.trade_ideas.distributions.retry',
                [
                    'tradeIdea' =>
                        $ideaB,

                    'distribution' =>
                        $distribution,
                ]
            )
        )->assertNotFound();
    }

    public function test_generic_share_card_is_protected_by_view_permission(): void
    {
        $creator = $this->adminWithPermissions([
            'trade_ideas.view',
        ]);

        $tradeIdea = $this->publishedTradeIdea(
            $creator
        );

        $this->actingAs(
            $creator,
            'admin'
        )->get(
            route(
                'admin.trade_ideas.share_card',
                [
                    'tradeIdea' =>
                        $tradeIdea,
                ]
            )
        )
            ->assertOk()
            ->assertHeader(
                'Content-Type',
                'image/svg+xml; charset=UTF-8'
            )
            ->assertSeeText(
                'TRADEX'
            )
            ->assertSeeText(
                'EURUSD'
            );

        $blocked = $this->adminWithPermissions(
            []
        );

        $this->actingAs(
            $blocked,
            'admin'
        )->get(
            route(
                'admin.trade_ideas.share_card',
                [
                    'tradeIdea' =>
                        $tradeIdea,
                ]
            )
        )->assertForbidden();
    }

    public function test_auto_distribution_on_publish_is_opt_in_and_queues_when_enabled(): void
    {
        Queue::fake();

        config([
            'tradex_trade_ideas.auto.on_publish' =>
                true,

            'tradex_trade_ideas.telegram.enabled' =>
                true,

            'tradex_trade_ideas.telegram.bot_token' =>
                'test-token',

            'tradex_trade_ideas.telegram.chat_ids' =>
                [
                    '@tradex_test',
                ],

            'tradex_trade_ideas.whatsapp.enabled' =>
                false,
        ]);

        $admin = $this->adminWithPermissions([
            'trade_ideas.publish',
        ]);

        $tradeIdea = $this->draftTradeIdea(
            $admin
        );

        $this->actingAs(
            $admin,
            'admin'
        )->patch(
            route(
                'admin.trade_ideas.publication',
                [
                    'tradeIdea' =>
                        $tradeIdea,
                ]
            ),
            []
        )->assertRedirect();

        $tradeIdea->refresh();

        $this->assertSame(
            TradeIdea::STATUS_PUBLISHED,
            $tradeIdea->status
        );

        $this->assertDatabaseHas(
            'trade_idea_distributions',
            [
                'trade_idea_id' =>
                    $tradeIdea->id,

                'event_type' =>
                    TradeIdeaDistribution::EVENT_INITIAL,

                'channel' =>
                    TradeIdeaDistribution::CHANNEL_TELEGRAM,
            ]
        );

        Queue::assertPushed(
            SendTelegramTradeIdea::class
        );
    }

    public function test_auto_distribution_on_timeline_update_is_opt_in(): void
    {
        Queue::fake();

        config([
            'tradex_trade_ideas.auto.on_update' =>
                true,

            'tradex_trade_ideas.telegram.enabled' =>
                true,

            'tradex_trade_ideas.telegram.bot_token' =>
                'test-token',

            'tradex_trade_ideas.telegram.chat_ids' =>
                [
                    '@tradex_test',
                ],

            'tradex_trade_ideas.whatsapp.enabled' =>
                false,
        ]);

        $admin = $this->adminWithPermissions([
            'trade_ideas.update',
        ]);

        $tradeIdea = $this->publishedTradeIdea(
            $admin
        );

        $this->actingAs(
            $admin,
            'admin'
        )->post(
            route(
                'admin.trade_ideas.updates.store',
                [
                    'tradeIdea' =>
                        $tradeIdea,
                ]
            ),
            [
                'type' =>
                    'NOTE',

                'message' =>
                    'Market structure update.',
            ]
        )->assertRedirect();

        $this->assertDatabaseHas(
            'trade_idea_distributions',
            [
                'trade_idea_id' =>
                    $tradeIdea->id,

                'event_type' =>
                    TradeIdeaDistribution::EVENT_UPDATE,

                'channel' =>
                    TradeIdeaDistribution::CHANNEL_TELEGRAM,
            ]
        );

        Queue::assertPushed(
            SendTelegramTradeIdea::class
        );
    }

    public function test_auto_distribution_on_close_is_opt_in(): void
    {
        Queue::fake();

        config([
            'tradex_trade_ideas.auto.on_close' =>
                true,

            'tradex_trade_ideas.telegram.enabled' =>
                true,

            'tradex_trade_ideas.telegram.bot_token' =>
                'test-token',

            'tradex_trade_ideas.telegram.chat_ids' =>
                [
                    '@tradex_test',
                ],

            'tradex_trade_ideas.whatsapp.enabled' =>
                false,
        ]);

        $admin = $this->adminWithPermissions([
            'trade_ideas.close',
        ]);

        $tradeIdea = $this->publishedTradeIdea(
            $admin
        );

        $this->actingAs(
            $admin,
            'admin'
        )->patch(
            route(
                'admin.trade_ideas.close',
                [
                    'tradeIdea' =>
                        $tradeIdea,
                ]
            ),
            [
                'outcome' =>
                    'CANCELLED',

                'note' =>
                    'Idea invalidated.',
            ]
        )->assertRedirect();

        $this->assertDatabaseHas(
            'trade_idea_distributions',
            [
                'trade_idea_id' =>
                    $tradeIdea->id,

                'event_type' =>
                    TradeIdeaDistribution::EVENT_CLOSED,

                'channel' =>
                    TradeIdeaDistribution::CHANNEL_TELEGRAM,
            ]
        );

        Queue::assertPushed(
            SendTelegramTradeIdea::class
        );
    }

    private function adminWithPermissions(
        array $codes
    ): AdminUser {
        $admin = AdminUser::query()->create([
            'uuid' =>
                Str::uuid()->toString(),

            'name' =>
                'ADMIN-05A Distribution Admin',

            'email' =>
                'admin05a-'
                .Str::lower(
                    Str::random(12)
                )
                .'@tradex.test',

            'email_verified_at' =>
                now(),

            'password' =>
                'TestingPassword123!',

            'is_active' =>
                true,

            'failed_login_attempts' =>
                0,

            'locked_until' =>
                null,
        ]);

        $role = AdminRole::query()->create([
            'code' =>
                'ADMIN05A_'
                .strtoupper(
                    Str::random(8)
                ),

            'name' =>
                'ADMIN-05A Role',

            'is_system' =>
                false,

            'is_active' =>
                true,
        ]);

        $admin->roles()->attach(
            $role->id
        );

        foreach (
            $codes
            as $code
        ) {
            $permission =
                AdminPermission::query()
                    ->firstOrCreate(
                        [
                            'code' =>
                                $code,
                        ],
                        [
                            'name' =>
                                $code,

                            'module' =>
                                'TRADE_IDEAS',
                        ]
                    );

            $role->permissions()
                ->attach(
                    $permission->id
                );
        }

        return $admin->fresh();
    }

    private function draftTradeIdea(
        AdminUser $admin,
        string $symbol = 'EURUSD'
    ): TradeIdea {
        return TradeIdea::query()->create([
            'title' =>
                $symbol
                .' Global Trade Idea',

            'summary' =>
                null,

            'analysis' =>
                null,

            'tradingview_url' =>
                'https://www.tradingview.com/chart/AbCdEf12/',

            'category' =>
                TradeIdea::CATEGORY_FOREX,

            'market_region' =>
                null,

            'symbol' =>
                $symbol,

            'instrument_name' =>
                null,

            'direction' =>
                TradeIdea::DIRECTION_BUY,

            'timeframe' =>
                'H1',

            'entry_price_low' =>
                null,

            'entry_price_high' =>
                null,

            'stop_loss' =>
                null,

            'current_stop_loss' =>
                null,

            'status' =>
                TradeIdea::STATUS_DRAFT,

            'published_at' =>
                null,

            'created_by_admin_id' =>
                $admin->id,

            'updated_by_admin_id' =>
                $admin->id,
        ]);
    }

    private function publishedTradeIdea(
        AdminUser $admin,
        string $symbol = 'EURUSD'
    ): TradeIdea {
        $tradeIdea =
            $this->draftTradeIdea(
                $admin,
                $symbol
            );

        $tradeIdea->forceFill([
            'status' =>
                TradeIdea::STATUS_PUBLISHED,

            'published_at' =>
                now(),
        ])->save();

        return $tradeIdea->fresh();
    }
}
