<?php

namespace Tests\Feature\Api\Education;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesEducationContent;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class EducationPlaybackTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTradeXMobileSession;
    use CreatesEducationContent;

    public function test_playback_token_is_issued_without_exposing_storage_path(): void
    {
        [
            ,
            ,
            ,
            $video,
        ] = $this->createEducationTree();

        $user =
            $this->createApiUser();

        $token =
            $this->issueBoundToken(
                $user
            );

        $response =
            $this
                ->withToken(
                    $token
                )
                ->postJson(
                    '/api/v1/education/videos/'
                    .$video->uuid
                    .'/playback-token'
                )
                ->assertOk()
                ->assertJsonPath(
                    'data.watermark.text',
                    $user->referral_code
                )
                ->assertJsonPath(
                    'data.content_protection.download_allowed',
                    false
                )
                ->assertJsonMissing([
                    'hls_master_path',
                ])
                ->assertJsonMissing([
                    'source_path',
                ]);

        $playbackUrl =
            $response->json(
                'data.playback_url'
            );

        $this
            ->get(
                $playbackUrl
            )
            ->assertOk()
            ->assertHeader(
                'Content-Type',
                'application/vnd.apple.mpegurl'
            )
            ->assertSee(
                '/api/v1/education/playback/',
                false
            )
            ->assertDontSee(
                'education/'
                .$video->lesson->uuid
                .'/segment0.ts',
                false
            );
    }

    public function test_free_user_cannot_issue_token_for_locked_premium_lesson(): void
    {
        [
            ,
            ,
            ,
            $video,
        ] = $this->createEducationTree(
            Course::ACCESS_PREMIUM,
            false
        );

        $user =
            $this->createApiUser([
                'account_type' =>
                    'FREE',
            ]);

        $token =
            $this->issueBoundToken(
                $user
            );

        $this
            ->withToken(
                $token
            )
            ->postJson(
                '/api/v1/education/videos/'
                .$video->uuid
                .'/playback-token'
            )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'EDUCATION_PREMIUM_REQUIRED'
            );
    }

    public function test_invalid_playback_token_is_rejected(): void
    {
        $this
            ->get(
                '/api/v1/education/playback/'
                .str_repeat(
                    'a',
                    64
                )
                .'/master.m3u8'
            )
            ->assertUnauthorized()
            ->assertJsonPath(
                'error.code',
                'INVALID_PLAYBACK_TOKEN'
            );
    }

    public function test_playback_token_stops_working_after_issuing_login_session_is_revoked(): void
    {
        [
            ,
            ,
            ,
            $video,
        ] = $this->createEducationTree();

        $user =
            $this->createApiUser();

        $accessToken =
            $this->issueBoundToken(
                $user
            );

        $response =
            $this
                ->withToken(
                    $accessToken
                )
                ->postJson(
                    '/api/v1/education/videos/'
                    .$video->uuid
                    .'/playback-token'
                )
                ->assertOk();

        $playbackUrl =
            $response->json(
                'data.playback_url'
            );

        $user->tokens()
            ->delete();

        $this
            ->get(
                $playbackUrl
            )
            ->assertUnauthorized()
            ->assertJsonPath(
                'error.code',
                'PLAYBACK_LOGIN_SESSION_ENDED'
            );
    }

}
