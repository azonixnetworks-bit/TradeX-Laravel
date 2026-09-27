<?php

namespace Tests\Feature\Admin\TradeIdeas;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\TradeIdea;
use App\Models\TradeIdeaTarget;
use App\Models\TradeIdeaUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TradeIdeaManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_trade_ideas(): void
    {
        $this->get(
            route(
                'admin.trade_ideas.index'
            )
        )->assertRedirect(
            route(
                'admin.login'
            )
        );
    }

    public function test_view_permission_is_required(): void
    {
        $admin = $this->adminWithPermissions(
            []
        );

        $this->actingAs(
            $admin,
            'admin'
        )->get(
            route(
                'admin.trade_ideas.index'
            )
        )->assertForbidden();
    }

    public function test_rev3_schema_removes_language_and_validity_fields(): void
    {
        $this->assertFalse(
            Schema::hasColumn(
                'trade_ideas',
                'language_code'
            )
        );

        $this->assertFalse(
            Schema::hasColumn(
                'trade_ideas',
                'valid_until'
            )
        );
    }

    public function test_admin_can_create_trade_idea_without_language_validity_structure_or_context(): void
    {
        $admin = $this->adminWithPermissions([
            'trade_ideas.view',
            'trade_ideas.create',
        ]);

        $response = $this->actingAs(
            $admin,
            'admin'
        )->post(
            route(
                'admin.trade_ideas.store'
            ),
            $this->minimalPayload()
        );

        $tradeIdea = TradeIdea::query()
            ->firstOrFail();

        $response->assertRedirect(
            route(
                'admin.trade_ideas.show',
                [
                    'tradeIdea' => $tradeIdea,
                ]
            )
        );

        $this->assertSame(
            TradeIdea::STATUS_DRAFT,
            $tradeIdea->status
        );

        $this->assertNull(
            $tradeIdea->summary
        );

        $this->assertNull(
            $tradeIdea->analysis
        );

        $this->assertNull(
            $tradeIdea->entry_price_low
        );

        $this->assertNull(
            $tradeIdea->entry_price_high
        );

        $this->assertNull(
            $tradeIdea->stop_loss
        );

        $this->assertSame(
            0,
            $tradeIdea->targets()->count()
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' => 'TRADE_IDEA_CREATED',
                'module' => 'TRADE_IDEAS',
                'target_id' => $tradeIdea->id,
            ]
        );
    }

    public function test_legacy_language_and_valid_until_fields_are_rejected(): void
    {
        $admin = $this->adminWithPermissions([
            'trade_ideas.create',
        ]);

        $payload = $this->minimalPayload();
        $payload['language_code'] = 'en';
        $payload['valid_until'] = now()
            ->addDay()
            ->format('Y-m-d H:i:s');

        $this->actingAs(
            $admin,
            'admin'
        )->from(
            route(
                'admin.trade_ideas.create'
            )
        )->post(
            route(
                'admin.trade_ideas.store'
            ),
            $payload
        )
            ->assertRedirect(
                route(
                    'admin.trade_ideas.create'
                )
            )
            ->assertSessionHasErrors([
                'language_code',
                'valid_until',
            ]);

        $this->assertDatabaseCount(
            'trade_ideas',
            0
        );
    }

    public function test_optional_trade_structure_is_validated_when_supplied(): void
    {
        $admin = $this->adminWithPermissions([
            'trade_ideas.create',
        ]);

        $payload = $this->minimalPayload();

        $payload['entry_price_low'] = '1.10000';
        $payload['entry_price_high'] = '1.10200';
        $payload['stop_loss'] = '1.10500';
        $payload['targets'] = [
            [
                'price' => '1.11000',
            ],
        ];

        $this->actingAs(
            $admin,
            'admin'
        )->from(
            route(
                'admin.trade_ideas.create'
            )
        )->post(
            route(
                'admin.trade_ideas.store'
            ),
            $payload
        )
            ->assertRedirect(
                route(
                    'admin.trade_ideas.create'
                )
            )
            ->assertSessionHasErrors(
                'stop_loss'
            );

        $this->assertDatabaseCount(
            'trade_ideas',
            0
        );
    }

    public function test_stock_idea_still_requires_market_region(): void
    {
        $admin = $this->adminWithPermissions([
            'trade_ideas.create',
        ]);

        $payload = $this->minimalPayload();
        $payload['category'] = 'STOCKS';
        $payload['symbol'] = 'JKH.N0000';

        $this->actingAs(
            $admin,
            'admin'
        )->from(
            route(
                'admin.trade_ideas.create'
            )
        )->post(
            route(
                'admin.trade_ideas.store'
            ),
            $payload
        )
            ->assertRedirect(
                route(
                    'admin.trade_ideas.create'
                )
            )
            ->assertSessionHasErrors(
                'market_region'
            );
    }

    public function test_admin_can_upload_private_chart_image_and_save_tradingview_link(): void
    {
        Storage::fake(
            'private'
        );

        $admin = $this->adminWithPermissions([
            'trade_ideas.view',
            'trade_ideas.create',
        ]);

        $payload = $this->minimalPayload();

        $payload['tradingview_url'] =
            'https://www.tradingview.com/chart/AbCdEf12/?symbol=OANDA%3AEURUSD';

        $payload['chart_image'] =
            UploadedFile::fake()->createWithContent(
                'chart.png',
                base64_decode(
                    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZsX8AAAAASUVORK5CYII='
                )
            );

        $this->actingAs(
            $admin,
            'admin'
        )->post(
            route(
                'admin.trade_ideas.store'
            ),
            $payload
        )->assertRedirect();

        $tradeIdea = TradeIdea::query()
            ->firstOrFail();

        $this->assertSame(
            'private',
            $tradeIdea->chart_image_disk
        );

        $this->assertNotNull(
            $tradeIdea->chart_image_path
        );

        Storage::disk(
            'private'
        )->assertExists(
            $tradeIdea->chart_image_path
        );

        $this->assertStringStartsWith(
            'https://www.tradingview.com/chart/',
            $tradeIdea->tradingview_url
        );
    }

    public function test_chart_image_preview_requires_trade_ideas_view(): void
    {
        Storage::fake(
            'private'
        );

        $creator = $this->adminWithPermissions([
            'trade_ideas.view',
            'trade_ideas.create',
        ]);

        $payload = $this->minimalPayload();

        $payload['chart_image'] =
            UploadedFile::fake()->createWithContent(
                'chart.png',
                base64_decode(
                    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZsX8AAAAASUVORK5CYII='
                )
            );

        $this->actingAs(
            $creator,
            'admin'
        )->post(
            route(
                'admin.trade_ideas.store'
            ),
            $payload
        )->assertRedirect();

        $tradeIdea = TradeIdea::query()
            ->firstOrFail();

        $viewer = $this->adminWithPermissions([
            'trade_ideas.view',
        ]);

        $this->actingAs(
            $viewer,
            'admin'
        )->get(
            route(
                'admin.trade_ideas.chart_image',
                [
                    'tradeIdea' => $tradeIdea,
                ]
            )
        )->assertOk();

        $blocked = $this->adminWithPermissions(
            []
        );

        $this->actingAs(
            $blocked,
            'admin'
        )->get(
            route(
                'admin.trade_ideas.chart_image',
                [
                    'tradeIdea' => $tradeIdea,
                ]
            )
        )->assertForbidden();
    }

    public function test_untrusted_tradingview_lookalike_url_is_rejected(): void
    {
        $admin = $this->adminWithPermissions([
            'trade_ideas.create',
        ]);

        $payload = $this->minimalPayload();

        $payload['tradingview_url'] =
            'https://tradingview.com.evil.example/chart/AbCdEf12/';

        $this->actingAs(
            $admin,
            'admin'
        )->from(
            route(
                'admin.trade_ideas.create'
            )
        )->post(
            route(
                'admin.trade_ideas.store'
            ),
            $payload
        )
            ->assertRedirect(
                route(
                    'admin.trade_ideas.create'
                )
            )
            ->assertSessionHasErrors(
                'tradingview_url'
            );
    }

    public function test_structureless_trade_idea_can_be_published(): void
    {
        $admin = $this->adminWithPermissions([
            'trade_ideas.publish',
        ]);

        $tradeIdea = $this->createMinimalDraft(
            $admin
        );

        $this->actingAs(
            $admin,
            'admin'
        )->patch(
            route(
                'admin.trade_ideas.publication',
                [
                    'tradeIdea' => $tradeIdea,
                ]
            ),
            [
                'reason' =>
                    'Global idea approved without optional structure.',
            ]
        )->assertRedirect(
            route(
                'admin.trade_ideas.show',
                [
                    'tradeIdea' => $tradeIdea,
                ]
            )
        );

        $tradeIdea->refresh();

        $this->assertSame(
            TradeIdea::STATUS_PUBLISHED,
            $tradeIdea->status
        );

        $this->assertNull(
            $tradeIdea->current_stop_loss
        );

        $this->assertNotNull(
            $tradeIdea->published_at
        );
    }

    public function test_published_trade_idea_cannot_be_edited_or_deleted(): void
    {
        $admin = $this->adminWithPermissions([
            'trade_ideas.view',
            'trade_ideas.update',
            'trade_ideas.delete',
        ]);

        $tradeIdea = $this->createMinimalPublished(
            $admin
        );

        $this->actingAs(
            $admin,
            'admin'
        )->get(
            route(
                'admin.trade_ideas.edit',
                [
                    'tradeIdea' => $tradeIdea,
                ]
            )
        )->assertStatus(
            422
        );

        $this->actingAs(
            $admin,
            'admin'
        )->delete(
            route(
                'admin.trade_ideas.destroy',
                [
                    'tradeIdea' => $tradeIdea,
                ]
            )
        )->assertStatus(
            422
        );
    }

    public function test_live_timeline_still_supports_entry_and_stop_without_initial_structure(): void
    {
        $admin = $this->adminWithPermissions([
            'trade_ideas.update',
        ]);

        $tradeIdea = $this->createMinimalPublished(
            $admin
        );

        $this->actingAs(
            $admin,
            'admin'
        )->post(
            route(
                'admin.trade_ideas.updates.store',
                [
                    'tradeIdea' => $tradeIdea,
                ]
            ),
            [
                'type' => 'ENTRY_TRIGGERED',
                'price' => '1.10150',
                'message' => 'Entry observed after publication.',
            ]
        )->assertRedirect();

        $this->actingAs(
            $admin,
            'admin'
        )->post(
            route(
                'admin.trade_ideas.updates.store',
                [
                    'tradeIdea' => $tradeIdea,
                ]
            ),
            [
                'type' => 'STOP_MOVED',
                'price' => '1.09900',
                'message' => 'Protective stop added.',
            ]
        )->assertRedirect();

        $tradeIdea->refresh();

        $this->assertSame(
            '1.1015000000',
            $tradeIdea->entry_price_actual
        );

        $this->assertSame(
            '1.0990000000',
            $tradeIdea->current_stop_loss
        );

        $this->assertDatabaseHas(
            'trade_idea_updates',
            [
                'trade_idea_id' => $tradeIdea->id,
                'type' => TradeIdeaUpdate::TYPE_ENTRY_TRIGGERED,
            ]
        );

        $this->assertDatabaseHas(
            'trade_idea_updates',
            [
                'trade_idea_id' => $tradeIdea->id,
                'type' => TradeIdeaUpdate::TYPE_STOP_MOVED,
            ]
        );
    }

    public function test_optional_target_hit_is_tracked_when_targets_exist(): void
    {
        $admin = $this->adminWithPermissions([
            'trade_ideas.update',
        ]);

        $tradeIdea = $this->createMinimalPublished(
            $admin
        );

        $target = TradeIdeaTarget::query()->create([
            'trade_idea_id' => $tradeIdea->id,
            'label' => 'TP1',
            'price' => '1.12000',
            'sort_order' => 1,
        ]);

        $this->actingAs(
            $admin,
            'admin'
        )->post(
            route(
                'admin.trade_ideas.updates.store',
                [
                    'tradeIdea' => $tradeIdea,
                ]
            ),
            [
                'type' => 'TARGET_HIT',
                'target_id' => $target->id,
                'message' => 'Optional target reached.',
            ]
        )->assertRedirect();

        $target->refresh();

        $this->assertNotNull(
            $target->hit_at
        );
    }

    public function test_close_permission_closes_structureless_published_idea(): void
    {
        $admin = $this->adminWithPermissions([
            'trade_ideas.close',
        ]);

        $tradeIdea = $this->createMinimalPublished(
            $admin
        );

        $this->actingAs(
            $admin,
            'admin'
        )->patch(
            route(
                'admin.trade_ideas.close',
                [
                    'tradeIdea' => $tradeIdea,
                ]
            ),
            [
                'outcome' => 'CANCELLED',
                'note' => 'Idea invalidated before a full structure was supplied.',
            ]
        )->assertRedirect();

        $tradeIdea->refresh();

        $this->assertSame(
            TradeIdea::STATUS_CLOSED,
            $tradeIdea->status
        );

        $this->assertSame(
            TradeIdea::OUTCOME_CANCELLED,
            $tradeIdea->close_outcome
        );

        $this->assertDatabaseHas(
            'trade_idea_updates',
            [
                'trade_idea_id' => $tradeIdea->id,
                'type' => TradeIdeaUpdate::TYPE_CLOSED,
            ]
        );
    }

    public function test_long_term_market_filter_separates_cse_india_and_international_stocks(): void
    {
        $admin = $this->adminWithPermissions([
            'trade_ideas.view',
        ]);

        $cse = $this->createMinimalDraft($admin);
        $cse->forceFill([
            'title' => 'CSE Market Idea',
            'category' => TradeIdea::CATEGORY_STOCKS,
            'market_region' => TradeIdea::REGION_SRI_LANKA,
            'symbol' => 'JKH.N0000',
        ])->save();

        $india = $this->createMinimalDraft($admin);
        $india->forceFill([
            'title' => 'India Market Idea',
            'category' => TradeIdea::CATEGORY_STOCKS,
            'market_region' => TradeIdea::REGION_INDIA,
            'symbol' => 'RELIANCE',
        ])->save();

        $international = $this->createMinimalDraft($admin);
        $international->forceFill([
            'title' => 'International Market Idea',
            'category' => TradeIdea::CATEGORY_STOCKS,
            'market_region' => TradeIdea::REGION_INTERNATIONAL,
            'symbol' => 'AAPL',
        ])->save();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.trade_ideas.index', [
                'market_filter' => 'STOCKS_INDIA',
            ]))
            ->assertOk()
            ->assertSee('India Market Idea')
            ->assertDontSee('CSE Market Idea')
            ->assertDontSee('International Market Idea')
            ->assertSee('Stocks · India');
    }

    public function test_long_term_today_filter_only_lists_ideas_created_today(): void
    {
        $admin = $this->adminWithPermissions([
            'trade_ideas.view',
        ]);

        $today = $this->createMinimalDraft($admin);
        $today->forceFill([
            'title' => 'Today Trade Idea',
        ])->save();

        $older = $this->createMinimalDraft($admin);
        $older->forceFill([
            'title' => 'Older Trade Idea',
            'created_at' => now()->subDays(2),
        ])->save();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.trade_ideas.index', [
                'market_filter' => 'TODAY',
            ]))
            ->assertOk()
            ->assertSee('Today Trade Idea')
            ->assertDontSee('Older Trade Idea');
    }

    private function minimalPayload(): array
    {
        return [
            'title' =>
                'EURUSD H1 Market Idea',

            'category' =>
                TradeIdea::CATEGORY_FOREX,

            'symbol' =>
                'eurusd',

            'direction' =>
                TradeIdea::DIRECTION_BUY,

            'timeframe' =>
                'H1',
        ];
    }

    private function adminWithPermissions(
        array $codes
    ): AdminUser {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'ADMIN-05 REV3 Admin',
            'email' =>
                'admin05-rev3-'
                .Str::lower(
                    Str::random(12)
                )
                .'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $role = AdminRole::query()->create([
            'code' =>
                'ADMIN05R3_'
                .strtoupper(
                    Str::random(8)
                ),
            'name' =>
                'ADMIN-05 REV3 Role',
            'is_system' => false,
            'is_active' => true,
        ]);

        $admin->roles()->attach(
            $role->id
        );

        foreach ($codes as $code) {
            $permission = AdminPermission::query()
                ->firstOrCreate(
                    [
                        'code' => $code,
                    ],
                    [
                        'name' => $code,
                        'module' => 'TRADE_IDEAS',
                    ]
                );

            $role->permissions()->attach(
                $permission->id
            );
        }

        return $admin->fresh();
    }

    private function createMinimalDraft(
        AdminUser $admin
    ): TradeIdea {
        return TradeIdea::query()->create([
            'title' => 'Minimal Global Trade Idea',
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
            'instrument_name' => null,
            'direction' => TradeIdea::DIRECTION_BUY,
            'timeframe' => 'H1',
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
            'created_by_admin_id' => $admin->id,
            'updated_by_admin_id' => $admin->id,
        ]);
    }

    private function createMinimalPublished(
        AdminUser $admin
    ): TradeIdea {
        $tradeIdea = $this->createMinimalDraft(
            $admin
        );

        $tradeIdea->forceFill([
            'status' => TradeIdea::STATUS_PUBLISHED,
            'published_at' => now(),
        ])->save();

        return $tradeIdea->fresh();
    }
}
