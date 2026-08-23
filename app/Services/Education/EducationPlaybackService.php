<?php

namespace App\Services\Education;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\EducationPlaybackSession;
use App\Models\LessonVideo;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EducationPlaybackService
{
    public function __construct(
        private readonly EducationAccessService
            $access
    ) {
    }

    public function issue(
        User $user,
        UserDevice $device,
        LessonVideo $video,
        string $sanctumSessionUuid,
        Request $request
    ): array {
        $lesson =
            $video->lesson;

        $lesson->loadMissing(
            'module.course'
        );

        $this->access
            ->assertLessonAccess(
                $user,
                $lesson
            );

        if (
            ! $video->isPlaybackReady()
            || ! $video->hasHlsStream()
        ) {
            throw new AuthApiException(
                409,
                'VIDEO_NOT_PLAYBACK_READY',
                'This video is not ready for protected HLS playback.'
            );
        }

        $plainToken =
            bin2hex(
                random_bytes(32)
            );

        $minutes =
            max(
                1,
                (int) config(
                    'tradex_education.playback.token_minutes',
                    5
                )
            );

        $session =
            EducationPlaybackSession::query()
                ->create([
                    'uuid' =>
                        (string) Str::uuid(),

                    'token_hash' =>
                        hash(
                            'sha256',
                            $plainToken
                        ),

                    'user_id' =>
                        $user->getKey(),

                    'user_device_id' =>
                        $device->getKey(),

                    'lesson_video_id' =>
                        $video->getKey(),

                    'sanctum_session_uuid' =>
                        $sanctumSessionUuid,

                    'request_ip' =>
                        $request->ip(),

                    'user_agent_hash' =>
                        filled(
                            $request->userAgent()
                        )
                            ? hash(
                                'sha256',
                                (string)
                                    $request
                                        ->userAgent()
                            )
                            : null,

                    'expires_at' =>
                        now()->addMinutes(
                            $minutes
                        ),

                    'use_count' =>
                        0,
                ]);

        AuditLog::record(
            'USER',
            $user->getKey(),
            'EDUCATION_PLAYBACK_TOKEN_ISSUED',
            'EDUCATION',
            'LESSON_VIDEO',
            $video->getKey(),
            null,
            [
                'playback_session_uuid' =>
                    $session->uuid,

                'expires_at' =>
                    $session
                        ->expires_at
                        ->toIso8601String(),
            ],
            $request->ip(),
            $request->userAgent()
        );

        return [
            'playback_session_uuid' =>
                $session->uuid,

            'playback_token' =>
                $plainToken,

            'playback_url' =>
                route(
                    'api.v1.education.playback.asset',
                    [
                        'token' =>
                            $plainToken,

                        'path' =>
                            basename(
                                (string)
                                    $video
                                        ->hls_master_path
                            ),
                    ]
                ),

            'expires_at' =>
                $session
                    ->expires_at
                    ->toIso8601String(),

            'duration_seconds' =>
                $video
                    ->duration_seconds,

            'watermark' => [
                'enabled' =>
                    true,

                'text' =>
                    $user
                        ->referral_code,

                'user_uuid' =>
                    $user
                        ->uuid,

                'dynamic_position' =>
                    true,
            ],

            'content_protection' => [
                'screenshot_deterrence' =>
                    true,

                'screen_recording_deterrence' =>
                    true,

                'download_allowed' =>
                    false,
            ],
        ];
    }

    public function resolveToken(
        string $plainToken,
        Request $request
    ): EducationPlaybackSession {
        $session =
            EducationPlaybackSession::query()
                ->where(
                    'token_hash',
                    hash(
                        'sha256',
                        $plainToken
                    )
                )
                ->with([
                    'user',
                    'device',
                    'video.lesson.module.course',
                ])
                ->first();

        if (
            ! $session
            || ! $session->isUsable()
        ) {
            throw new AuthApiException(
                401,
                'INVALID_PLAYBACK_TOKEN',
                'The playback token is invalid or expired.'
            );
        }

        $maxRequests =
            max(
                1,
                (int) config(
                    'tradex_education.playback.max_asset_requests',
                    1000
                )
            );

        if (
            $session->use_count
            >= $maxRequests
        ) {
            $session->forceFill([
                'revoked_at' =>
                    now(),
            ])->save();

            throw new AuthApiException(
                429,
                'PLAYBACK_REQUEST_LIMIT_EXCEEDED',
                'The playback session has reached its request limit.'
            );
        }

        if (
            (bool) config(
                'tradex_education.playback.bind_user_agent',
                false
            )
            && filled(
                $session
                    ->user_agent_hash
            )
        ) {
            $currentHash =
                hash(
                    'sha256',
                    (string)
                        $request
                            ->userAgent()
                );

            if (
                ! hash_equals(
                    (string)
                        $session
                            ->user_agent_hash,
                    $currentHash
                )
            ) {
                throw new AuthApiException(
                    401,
                    'PLAYBACK_CLIENT_MISMATCH',
                    'The playback token does not match this client.'
                );
            }
        }

        $user =
            $session->user;

        $device =
            $session->device;

        $video =
            $session->video;

        if (
            ! $user
            || ! $user->canLogin()
            || ! $device
            || ! $device->is_active
            || ! $device->is_trusted
            || $device->isRevoked()
            || ! $video
            || ! $video->isPlaybackReady()
            || ! $video->hasHlsStream()
        ) {
            throw new AuthApiException(
                403,
                'PLAYBACK_ACCESS_REVOKED',
                'Playback access is no longer available.'
            );
        }

        $activeSanctumSession =
            $user->tokens()
                ->where(
                    'user_device_id',
                    $session->user_device_id
                )
                ->where(
                    'session_uuid',
                    $session->sanctum_session_uuid
                )
                ->where(
                    function (
                        $query
                    ): void {
                        $query
                            ->whereNull(
                                'expires_at'
                            )
                            ->orWhere(
                                'expires_at',
                                '>',
                                now()
                            );
                    }
                )
                ->exists();

        if (! $activeSanctumSession) {
            throw new AuthApiException(
                401,
                'PLAYBACK_LOGIN_SESSION_ENDED',
                'The login session that issued this playback token has ended.'
            );
        }

        $this->access
            ->assertLessonAccess(
                $user,
                $video->lesson
            );

        $session->forceFill([
            'last_used_at' =>
                now(),

            'use_count' =>
                $session->use_count
                + 1,
        ])->save();

        return $session;
    }
}
