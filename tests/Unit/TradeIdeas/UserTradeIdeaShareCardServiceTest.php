<?php

namespace Tests\Unit\TradeIdeas;

use App\Models\TradeIdea;
use App\Models\User;
use App\Services\TradeIdeas\UserTradeIdeaShareCardService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class UserTradeIdeaShareCardServiceTest extends TestCase
{
    public function test_active_premium_user_receives_personalized_share_card(): void
    {
        config([
            'tradex_trade_ideas.share.affiliate_base_url' =>
                'https://app.tradex.test/register',
        ]);

        $user =
            $this->premiumUser(
                referralCode:
                    'USER123'
            );

        $tradeIdea =
            $this->publishedTradeIdea();

        $svg =
            app(
                UserTradeIdeaShareCardService::class
            )->render(
                $user,
                $tradeIdea,
                'portrait'
            );

        $this->assertStringContainsString(
            'USER123',
            $svg
        );

        $this->assertStringContainsString(
            'https://app.tradex.test/register?ref=USER123',
            $svg
        );

        $this->assertStringContainsString(
            'EURUSD',
            $svg
        );

        $this->assertStringContainsString(
            'viewBox="0 0 1080 1350"',
            $svg
        );
    }

    public function test_free_user_cannot_generate_personalized_trade_idea_share_card(): void
    {
        $user =
            $this->testUser(
                canAccessPremium:
                    false,

                accountType:
                    'FREE',

                status:
                    'ACTIVE',

                referralCode:
                    'FREE123'
            );

        $this->expectException(
            AuthorizationException::class
        );

        app(
            UserTradeIdeaShareCardService::class
        )->render(
            $user,
            $this->publishedTradeIdea()
        );
    }

    public function test_inactive_premium_user_cannot_generate_share_card(): void
    {
        $user =
            $this->testUser(
                canAccessPremium:
                    false,

                accountType:
                    'PREMIUM',

                status:
                    'SUSPENDED',

                referralCode:
                    'LOCKED123'
            );

        $this->expectException(
            AuthorizationException::class
        );

        app(
            UserTradeIdeaShareCardService::class
        )->render(
            $user,
            $this->publishedTradeIdea()
        );
    }

    public function test_draft_trade_idea_cannot_be_shared_to_user(): void
    {
        $tradeIdea =
            $this->publishedTradeIdea();

        $tradeIdea->status =
            TradeIdea::STATUS_DRAFT;

        $tradeIdea->published_at =
            null;

        $this->expectException(
            AuthorizationException::class
        );

        app(
            UserTradeIdeaShareCardService::class
        )->render(
            $this->premiumUser(),
            $tradeIdea
        );
    }

    public function test_missing_referral_code_is_rejected_instead_of_generating_generic_card(): void
    {
        $this->expectException(
            \RuntimeException::class
        );

        app(
            UserTradeIdeaShareCardService::class
        )->render(
            $this->premiumUser(
                referralCode:
                    null
            ),
            $this->publishedTradeIdea()
        );
    }

    public function test_closed_trade_idea_can_still_be_shared(): void
    {
        $tradeIdea =
            $this->publishedTradeIdea();

        $tradeIdea->status =
            TradeIdea::STATUS_CLOSED;

        $tradeIdea->closed_at =
            now();

        $svg =
            app(
                UserTradeIdeaShareCardService::class
            )->render(
                $this->premiumUser(),
                $tradeIdea,
                'square'
            );

        $this->assertStringContainsString(
            'viewBox="0 0 1080 1080"',
            $svg
        );

        $this->assertStringContainsString(
            'USER123',
            $svg
        );
    }

    private function premiumUser(
        ?string $referralCode = 'USER123'
    ): User {
        return $this->testUser(
            canAccessPremium:
                true,

            accountType:
                'PREMIUM',

            status:
                'ACTIVE',

            referralCode:
                $referralCode
        );
    }

    private function testUser(
        bool $canAccessPremium,
        string $accountType,
        string $status,
        ?string $referralCode
    ): User {
        /** @var User $user */
        $user =
            Mockery::mock(
                User::class
            )->makePartial();

        $user->forceFill([
            'account_type' =>
                $accountType,

            'status' =>
                $status,

            'referral_code' =>
                $referralCode,
        ]);

        $user->shouldReceive(
            'canAccessPremium'
        )->andReturn(
            $canAccessPremium
        );

        return $user;
    }

    private function publishedTradeIdea(): TradeIdea
    {
        $tradeIdea =
            new TradeIdea([
                'title' =>
                    'EURUSD London continuation',

                'summary' =>
                    'Liquidity sweep and continuation confirmation.',

                'category' =>
                    TradeIdea::CATEGORY_FOREX,

                'symbol' =>
                    'EURUSD',

                'direction' =>
                    TradeIdea::DIRECTION_BUY,

                'timeframe' =>
                    'H1',

                'entry_price_low' =>
                    '1.1700000000',

                'entry_price_high' =>
                    '1.1720000000',

                'stop_loss' =>
                    '1.1650000000',

                'status' =>
                    TradeIdea::STATUS_PUBLISHED,

                'published_at' =>
                    now(),
            ]);

        $tradeIdea->setRelation(
            'targets',
            new Collection()
        );

        return $tradeIdea;
    }
}
