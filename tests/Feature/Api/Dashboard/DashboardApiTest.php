<?php

namespace Tests\Feature\Api\Dashboard;

use App\Models\AffiliateStage;
use App\Models\ChannelVideo;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\TradeIdea;
use App\Models\UserLessonProgress;
use App\Services\Affiliates\AffiliateCommercialService;
use Database\Seeders\AffiliateStageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_premium_dashboard_uses_real_affiliate_learning_trade_idea_and_channel_data(): void
    {
        $this->seed(
            AffiliateStageSeeder::class
        );

        config()->set(
            'tradex_trade_ideas.share.affiliate_base_url',
            'https://tradex.example/register'
        );

        $affiliate = $this->createApiUser([
            'name' => 'Dashboard Premium',
            'account_type' => 'PREMIUM',
            'referral_code' => 'TXDASH001',
            'language_code' => 'en',
        ]);

        $buyer = $this->createApiUser([
            'referred_by_user_id' =>
                $affiliate->getKey(),
        ]);

        $this->createApiUser([
            'referred_by_user_id' =>
                $affiliate->getKey(),
        ]);

        app(
            AffiliateCommercialService::class
        )->recordClearedPayPalSale(
            $affiliate,
            $buyer,
            'PAYPAL-DASH-001',
            10000
        );

        [$course, $module, $lesson] =
            $this->createPublishedLesson(
                Course::ACCESS_PREMIUM,
                false
            );

        UserLessonProgress::query()->create([
            'user_id' =>
                $affiliate->getKey(),
            'lesson_id' =>
                $lesson->getKey(),
            'status' =>
                UserLessonProgress::STATUS_IN_PROGRESS,
            'last_position_seconds' => 321,
            'started_at' => now()->subMinutes(20),
            'last_activity_at' => now()->subMinute(),
        ]);

        $tradeIdea = TradeIdea::query()->create([
            'title' => 'EURUSD Dashboard Idea',
            'summary' => 'Premium dashboard summary',
            'category' => TradeIdea::CATEGORY_FOREX,
            'symbol' => 'EURUSD',
            'direction' => TradeIdea::DIRECTION_BUY,
            'timeframe' => 'H1',
            'status' => TradeIdea::STATUS_PUBLISHED,
            'published_at' => now()->subMinutes(5),
        ]);

        $channelVideo = ChannelVideo::query()->create([
            'title' => 'Latest English Video',
            'youtube_video_id' => 'dQw4w9WgXcQ',
            'youtube_url' =>
                'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'language_code' => 'en',
            'status' => ChannelVideo::STATUS_PUBLISHED,
            'published_at' => now()->subMinutes(2),
            'is_featured' => true,
        ]);

        $token = $this->issueBoundToken(
            $affiliate
        );

        $response = $this
            ->withToken($token)
            ->getJson(
                '/api/v1/dashboard'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.account.uuid',
                $affiliate->uuid
            )
            ->assertJsonPath(
                'data.account.account_type',
                'PREMIUM'
            )
            ->assertJsonPath(
                'data.referral.code',
                'TXDASH001'
            )
            ->assertJsonPath(
                'data.referral.url',
                'https://tradex.example/register?ref=TXDASH001'
            )
            ->assertJsonPath(
                'data.referral.direct_referrals_count',
                2
            )
            ->assertJsonPath(
                'data.affiliate.ledger_initialized',
                true
            )
            ->assertJsonPath(
                'data.affiliate.qualified_sales_count',
                1
            )
            ->assertJsonPath(
                'data.affiliate.current_stage.code',
                AffiliateStage::STARTER
            )
            ->assertJsonPath(
                'data.affiliate.commission.next_sale_rate_percent',
                '15.00'
            )
            ->assertJsonPath(
                'data.affiliate.financials.qualified_net_eligible_revenue.amount_cents',
                10000
            )
            ->assertJsonPath(
                'data.affiliate.financials.lifetime_commission_earned.amount_cents',
                1500
            )
            ->assertJsonPath(
                'data.affiliate.financials.net_commission.amount_cents',
                1500
            )
            ->assertJsonPath(
                'data.affiliate.milestones.next.sales_target',
                30
            )
            ->assertJsonPath(
                'data.affiliate.milestones.next.gift.amount_cents',
                7500
            )
            ->assertJsonPath(
                'data.learning.accessible_published_lessons',
                1
            )
            ->assertJsonPath(
                'data.learning.is_premium',
                true
            )
            ->assertJsonPath(
                'data.learning.in_progress_lessons',
                1
            )
            ->assertJsonPath(
                'data.learning.continue_learning.course.uuid',
                $course->uuid
            )
            ->assertJsonPath(
                'data.learning.continue_learning.module.uuid',
                $module->uuid
            )
            ->assertJsonPath(
                'data.learning.continue_learning.lesson.uuid',
                $lesson->uuid
            )
            ->assertJsonPath(
                'data.learning.continue_learning.last_position_seconds',
                321
            )
            ->assertJsonPath(
                'data.learning.continue_learning.progress_percent',
                0
            )
            ->assertJsonPath(
                'data.trade_ideas.latest.uuid',
                $tradeIdea->uuid
            )
            ->assertJsonPath(
                'data.our_channel.latest_video.uuid',
                $channelVideo->uuid
            )
            ->assertJsonPath(
                'data.our_channel.latest_video.youtube.privacy_enhanced_embed',
                true
            );

        $this->assertNoInternalIdKey(
            $response->json('data')
        );
    }

    public function test_free_dashboard_hides_trade_idea_content_and_counts_only_accessible_learning(): void
    {
        $this->seed(
            AffiliateStageSeeder::class
        );

        $freeUser = $this->createApiUser([
            'account_type' => 'FREE',
            'language_code' => 'ta',
        ]);

        $this->createPublishedLesson(
            Course::ACCESS_FREE,
            false
        );

        $this->createPublishedLesson(
            Course::ACCESS_PREMIUM,
            true
        );

        $this->createPublishedLesson(
            Course::ACCESS_PREMIUM,
            false
        );

        TradeIdea::query()->create([
            'title' => 'Must Stay Hidden',
            'category' => TradeIdea::CATEGORY_CRYPTO,
            'symbol' => 'BTCUSD',
            'direction' => TradeIdea::DIRECTION_BUY,
            'timeframe' => 'H4',
            'status' => TradeIdea::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        $tamilVideo = ChannelVideo::query()->create([
            'title' => 'Tamil Channel Video',
            'youtube_video_id' => 'dQw4w9WgXcQ',
            'youtube_url' =>
                'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'language_code' => 'ta',
            'status' => ChannelVideo::STATUS_PUBLISHED,
            'published_at' => now()->subMinutes(3),
        ]);

        ChannelVideo::query()->create([
            'title' => 'Newer Other Language',
            'youtube_video_id' => 'M7lc1UVf-VE',
            'youtube_url' =>
                'https://www.youtube.com/watch?v=M7lc1UVf-VE',
            'language_code' => 'en',
            'status' => ChannelVideo::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        $token = $this->issueBoundToken(
            $freeUser
        );

        $response = $this
            ->withToken($token)
            ->getJson(
                '/api/v1/dashboard'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.account.premium_access',
                false
            )
            ->assertJsonPath(
                'data.affiliate.ledger_initialized',
                false
            )
            ->assertJsonPath(
                'data.affiliate.qualified_sales_count',
                0
            )
            ->assertJsonPath(
                'data.affiliate.current_stage',
                null
            )
            ->assertJsonPath(
                'data.affiliate.commission.next_sale_rate_percent',
                '15.00'
            )
            ->assertJsonPath(
                'data.learning.accessible_published_lessons',
                2
            )
            ->assertJsonPath(
                'data.learning.is_premium',
                false
            )
            ->assertJsonCount(
                1,
                'data.learning.free_lessons'
            )
            ->assertJsonPath(
                'data.learning.free_lessons.0.course.is_free',
                true
            )
            ->assertJsonPath(
                'data.trade_ideas.available',
                false
            )
            ->assertJsonPath(
                'data.trade_ideas.premium_required',
                true
            )
            ->assertJsonPath(
                'data.trade_ideas.latest',
                null
            )
            ->assertJsonPath(
                'data.our_channel.matched_preferred_language',
                true
            )
            ->assertJsonPath(
                'data.our_channel.latest_video.uuid',
                $tamilVideo->uuid
            );

        $payload = json_encode(
            $response->json('data'),
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            'Must Stay Hidden',
            $payload
        );
    }

    public function test_dashboard_does_not_expose_draft_future_or_internal_resource_fields(): void
    {
        $this->seed(
            AffiliateStageSeeder::class
        );

        $user = $this->createApiUser([
            'account_type' => 'PREMIUM',
        ]);

        TradeIdea::query()->create([
            'title' => 'Future Idea',
            'category' => TradeIdea::CATEGORY_FOREX,
            'symbol' => 'GBPUSD',
            'direction' => TradeIdea::DIRECTION_SELL,
            'timeframe' => 'H1',
            'status' => TradeIdea::STATUS_PUBLISHED,
            'published_at' => now()->addHour(),
        ]);

        ChannelVideo::query()->create([
            'title' => 'Draft Video',
            'youtube_video_id' => 'dQw4w9WgXcQ',
            'youtube_url' =>
                'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'language_code' => 'en',
            'status' => ChannelVideo::STATUS_DRAFT,
            'published_at' => null,
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        $response = $this
            ->withToken($token)
            ->getJson(
                '/api/v1/dashboard'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.trade_ideas.latest',
                null
            )
            ->assertJsonPath(
                'data.our_channel.latest_video',
                null
            );

        $this->assertNoInternalIdKey(
            $response->json('data')
        );
    }

    public function test_dashboard_prefers_the_most_recently_watched_lesson(): void
    {
        $user = $this->createApiUser([
            'account_type' => 'PREMIUM',
        ]);

        [, , $olderLesson] = $this->createPublishedLesson(
            Course::ACCESS_PREMIUM,
            false
        );
        [, , $newerLesson] = $this->createPublishedLesson(
            Course::ACCESS_PREMIUM,
            false
        );

        UserLessonProgress::query()->create([
            'user_id' => $user->getKey(),
            'lesson_id' => $olderLesson->getKey(),
            'status' => UserLessonProgress::STATUS_IN_PROGRESS,
            'started_at' => now()->subHour(),
            'last_activity_at' => now()->subMinutes(10),
        ]);
        UserLessonProgress::query()->create([
            'user_id' => $user->getKey(),
            'lesson_id' => $newerLesson->getKey(),
            'status' => UserLessonProgress::STATUS_IN_PROGRESS,
            'started_at' => now()->subHour(),
            'last_activity_at' => now()->subMinute(),
        ]);

        $this->withToken($this->issueBoundToken($user))
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath(
                'data.learning.continue_learning.lesson.uuid',
                $newerLesson->uuid
            );
    }

    public function test_premium_dashboard_supplies_a_starting_lesson_without_history(): void
    {
        $user = $this->createApiUser([
            'account_type' => 'PREMIUM',
        ]);
        [, , $lesson] = $this->createPublishedLesson(
            Course::ACCESS_PREMIUM,
            false
        );

        $this->withToken($this->issueBoundToken($user))
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.learning.continue_learning', null)
            ->assertJsonPath(
                'data.learning.default_premium_lesson.lesson.uuid',
                $lesson->uuid
            );
    }

    private function createPublishedLesson(
        string $accessLevel,
        bool $isPreview
    ): array {
        $suffix = Str::lower(
            Str::random(8)
        );

        $course = Course::query()->create([
            'code' => 'COURSE-'.$suffix,
            'slug' => 'course-'.$suffix,
            'title' => 'Course '.$suffix,
            'language_code' => 'en',
            'access_level' => $accessLevel,
            'status' => Course::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        $module = CourseModule::query()->create([
            'course_id' => $course->getKey(),
            'code' => 'MODULE-'.$suffix,
            'slug' => 'module-'.$suffix,
            'title' => 'Module '.$suffix,
            'status' => CourseModule::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        $lesson = Lesson::query()->create([
            'course_module_id' => $module->getKey(),
            'code' => 'LESSON-'.$suffix,
            'slug' => 'lesson-'.$suffix,
            'title' => 'Lesson '.$suffix,
            'is_preview' => $isPreview,
            'status' => Lesson::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        return [
            $course,
            $module,
            $lesson,
        ];
    }

    private function assertNoInternalIdKey(
        mixed $value
    ): void {
        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $nested) {
            if (is_string($key)) {
                $this->assertNotSame(
                    'id',
                    $key,
                    'Dashboard API must not expose internal numeric id fields.'
                );
            }

            $this->assertNoInternalIdKey(
                $nested
            );
        }
    }
}
