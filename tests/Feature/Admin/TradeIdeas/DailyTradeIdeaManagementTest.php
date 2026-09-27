<?php

namespace Tests\Feature\Admin\TradeIdeas;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\DailyTradeIdea;
use App\Models\TradeIdea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DailyTradeIdeaManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_daily_trade_ideas(): void
    {
        $this->get(route('admin.daily_trade_ideas.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_view_permission_is_required(): void
    {
        $admin = $this->adminWithPermissions([]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.daily_trade_ideas.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_daily_trade_idea_without_touching_long_term_table(): void
    {
        $admin = $this->adminWithPermissions([
            'trade_ideas.view',
            'trade_ideas.create',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.daily_trade_ideas.store'), $this->minimalPayload());

        $idea = DailyTradeIdea::query()->firstOrFail();

        $response->assertRedirect(
            route('admin.daily_trade_ideas.show', ['dailyTradeIdea' => $idea])
        );

        $this->assertSame(DailyTradeIdea::STATUS_DRAFT, $idea->status);
        $this->assertDatabaseCount('trade_ideas', 0);
        $this->assertDatabaseCount('daily_trade_ideas', 1);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'DAILY_TRADE_IDEA_CREATED',
            'module' => 'TRADE_IDEAS',
            'target_id' => $idea->id,
        ]);
    }

    public function test_daily_trade_idea_uses_existing_trade_structure_validation(): void
    {
        $admin = $this->adminWithPermissions(['trade_ideas.create']);

        $payload = $this->minimalPayload();
        $payload['entry_price_low'] = '1.10000';
        $payload['entry_price_high'] = '1.10200';
        $payload['stop_loss'] = '1.10500';
        $payload['targets'] = [['price' => '1.11000']];

        $this->actingAs($admin, 'admin')
            ->from(route('admin.daily_trade_ideas.create'))
            ->post(route('admin.daily_trade_ideas.store'), $payload)
            ->assertRedirect(route('admin.daily_trade_ideas.create'))
            ->assertSessionHasErrors('stop_loss');

        $this->assertDatabaseCount('daily_trade_ideas', 0);
    }

    public function test_publish_and_archive_lifecycle_is_separate_and_audited(): void
    {
        $admin = $this->adminWithPermissions([
            'trade_ideas.view',
            'trade_ideas.publish',
            'trade_ideas.close',
        ]);

        $idea = $this->createDraft($admin);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.daily_trade_ideas.publish', ['dailyTradeIdea' => $idea]))
            ->assertRedirect();

        $idea->refresh();
        $this->assertSame(DailyTradeIdea::STATUS_PUBLISHED, $idea->status);
        $this->assertNotNull($idea->published_at);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.daily_trade_ideas.archive', ['dailyTradeIdea' => $idea]))
            ->assertRedirect();

        $idea->refresh();
        $this->assertSame(DailyTradeIdea::STATUS_ARCHIVED, $idea->status);
        $this->assertNotNull($idea->archived_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'DAILY_TRADE_IDEA_PUBLISHED',
            'target_id' => $idea->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'DAILY_TRADE_IDEA_ARCHIVED',
            'target_id' => $idea->id,
        ]);
    }

    public function test_only_draft_daily_trade_idea_can_be_edited(): void
    {
        $admin = $this->adminWithPermissions([
            'trade_ideas.update',
        ]);

        $idea = $this->createDraft($admin);
        $idea->update([
            'status' => DailyTradeIdea::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.daily_trade_ideas.edit', ['dailyTradeIdea' => $idea]))
            ->assertStatus(409);
    }

    public function test_existing_long_term_trade_idea_model_contract_remains_unchanged(): void
    {
        $this->assertSame('DRAFT', TradeIdea::STATUS_DRAFT);
        $this->assertSame('PUBLISHED', TradeIdea::STATUS_PUBLISHED);
        $this->assertSame('CLOSED', TradeIdea::STATUS_CLOSED);
        $this->assertFalse(in_array('ARCHIVED', [
            TradeIdea::STATUS_DRAFT,
            TradeIdea::STATUS_PUBLISHED,
            TradeIdea::STATUS_CLOSED,
        ], true));
    }

    public function test_daily_market_filter_separates_india_stocks(): void
    {
        $admin = $this->adminWithPermissions([
            'trade_ideas.view',
        ]);

        $cse = $this->createDraft($admin);
        $cse->forceFill([
            'title' => 'Daily CSE Idea',
            'category' => TradeIdea::CATEGORY_STOCKS,
            'market_region' => TradeIdea::REGION_SRI_LANKA,
            'symbol' => 'JKH.N0000',
        ])->save();

        $india = $this->createDraft($admin);
        $india->forceFill([
            'title' => 'Daily India Idea',
            'category' => TradeIdea::CATEGORY_STOCKS,
            'market_region' => TradeIdea::REGION_INDIA,
            'symbol' => 'RELIANCE',
        ])->save();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.daily_trade_ideas.index', [
                'market_filter' => 'STOCKS_INDIA',
            ]))
            ->assertOk()
            ->assertSee('Daily India Idea')
            ->assertDontSee('Daily CSE Idea')
            ->assertSee('Stocks · India');
    }

    public function test_daily_today_filter_only_lists_ideas_created_today(): void
    {
        $admin = $this->adminWithPermissions([
            'trade_ideas.view',
        ]);

        $today = $this->createDraft($admin);
        $today->forceFill([
            'title' => 'Daily Today Idea',
        ])->save();

        $older = $this->createDraft($admin);
        $older->forceFill([
            'title' => 'Daily Older Idea',
            'created_at' => now()->subDays(2),
        ])->save();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.daily_trade_ideas.index', [
                'market_filter' => 'TODAY',
            ]))
            ->assertOk()
            ->assertSee('Daily Today Idea')
            ->assertDontSee('Daily Older Idea');
    }

    private function minimalPayload(): array
    {
        return [
            'title' => 'EURUSD Daily Market Idea',
            'category' => TradeIdea::CATEGORY_FOREX,
            'symbol' => 'eurusd',
            'direction' => TradeIdea::DIRECTION_BUY,
            'timeframe' => 'H1',
        ];
    }

    private function createDraft(AdminUser $admin): DailyTradeIdea
    {
        return DailyTradeIdea::query()->create([
            'title' => 'Daily Draft',
            'category' => TradeIdea::CATEGORY_FOREX,
            'symbol' => 'EURUSD',
            'direction' => TradeIdea::DIRECTION_BUY,
            'timeframe' => 'H1',
            'status' => DailyTradeIdea::STATUS_DRAFT,
            'created_by_admin_id' => $admin->id,
            'updated_by_admin_id' => $admin->id,
        ]);
    }

    private function adminWithPermissions(array $codes): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'ADMIN-21 Admin',
            'email' => 'admin21-'.Str::lower(Str::random(12)).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'ADMIN21_'.strtoupper(Str::random(8)),
            'name' => 'ADMIN-21 Role',
            'is_system' => false,
            'is_active' => true,
        ]);

        $admin->roles()->attach($role->id);

        foreach ($codes as $code) {
            $permission = AdminPermission::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $code,
                    'module' => 'TRADE_IDEAS',
                ]
            );

            $role->permissions()->attach($permission->id);
        }

        return $admin->fresh();
    }
}
