<?php

namespace App\Services\OurChannel;

use App\Exceptions\ChannelConnectionException;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\ChannelConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class YouTubeOAuthService
{
    private const SESSION_KEY =
        'our_channel.youtube_oauth';

    public function authorizationUrl(
        ChannelConnection $connection,
        AdminUser $admin,
        Request $request,
        string $redirectUri
    ): string {
        $credentials = $this->credentials(
            $connection
        );

        $clientId = trim(
            (string) (
                $credentials['client_id']
                ?? ''
            )
        );

        $clientSecret = trim(
            (string) (
                $credentials['client_secret']
                ?? ''
            )
        );

        if ($clientId === '' || $clientSecret === '') {
            throw new ChannelConnectionException(
                'OAUTH_CLIENT_MISSING',
                'Save the Google OAuth Client ID and Client Secret before connecting YouTube.'
            );
        }

        $state = Str::random(64);

        $request->session()->put(
            self::SESSION_KEY,
            [
                'state_hash' =>
                    hash('sha256', $state),
                'expires_at' =>
                    now()->addMinutes(10)->timestamp,
                'admin_id' =>
                    $admin->getKey(),
                'connection_version' =>
                    (int) $connection->version,
                'redirect_uri' =>
                    $redirectUri,
            ]
        );

        $config = (array) config(
            'tradex_channels.platforms.youtube',
            []
        );

        $authorizationUrl = trim(
            (string) (
                $config['authorization_url']
                ?? 'https://accounts.google.com/o/oauth2/v2/auth'
            )
        );

        $scopes = (array) (
            $config['scopes']
            ?? [
                'https://www.googleapis.com/auth/youtube.upload',
                'https://www.googleapis.com/auth/youtube.readonly',
            ]
        );

        return $authorizationUrl
            .'?'
            .http_build_query(
                [
                    'client_id' => $clientId,
                    'redirect_uri' => $redirectUri,
                    'response_type' => 'code',
                    'scope' => implode(' ', $scopes),
                    'access_type' => 'offline',
                    'include_granted_scopes' => 'true',
                    'prompt' => 'consent',
                    'state' => $state,
                ],
                '',
                '&',
                PHP_QUERY_RFC3986
            );
    }

    public function complete(
        ChannelConnection $connection,
        AdminUser $admin,
        Request $request
    ): ChannelConnection {
        $oauth = $request->session()->pull(
            self::SESSION_KEY
        );

        if (! is_array($oauth)) {
            throw new ChannelConnectionException(
                'OAUTH_SESSION_MISSING',
                'The YouTube OAuth session expired. Start the connection again.'
            );
        }

        if (
            (int) ($oauth['admin_id'] ?? 0)
            !== (int) $admin->getKey()
        ) {
            throw new ChannelConnectionException(
                'OAUTH_ADMIN_MISMATCH',
                'The YouTube OAuth session does not belong to this admin session.'
            );
        }

        if (
            (int) ($oauth['expires_at'] ?? 0)
            < now()->timestamp
        ) {
            throw new ChannelConnectionException(
                'OAUTH_SESSION_EXPIRED',
                'The YouTube OAuth session expired. Start the connection again.'
            );
        }

        $state = trim(
            (string) $request->query(
                'state',
                ''
            )
        );

        $expectedStateHash = trim(
            (string) (
                $oauth['state_hash']
                ?? ''
            )
        );

        if (
            $state === ''
            || $expectedStateHash === ''
            || ! hash_equals(
                $expectedStateHash,
                hash('sha256', $state)
            )
        ) {
            throw new ChannelConnectionException(
                'OAUTH_STATE_INVALID',
                'Google OAuth state verification failed. Start the connection again.'
            );
        }

        $providerError = trim(
            (string) $request->query(
                'error',
                ''
            )
        );

        if ($providerError !== '') {
            throw new ChannelConnectionException(
                'OAUTH_'.$providerError,
                'Google did not grant the requested YouTube permissions.'
            );
        }

        $code = trim(
            (string) $request->query(
                'code',
                ''
            )
        );

        if ($code === '') {
            throw new ChannelConnectionException(
                'OAUTH_CODE_MISSING',
                'Google OAuth did not return an authorization code.'
            );
        }

        $connection->refresh();

        if (
            (int) $connection->version
            !== (int) (
                $oauth['connection_version']
                ?? -1
            )
        ) {
            throw new ChannelConnectionException(
                'OAUTH_CONFIGURATION_CHANGED',
                'YouTube connection settings changed during authorization. Start the connection again.'
            );
        }

        $redirectUri = trim(
            (string) (
                $oauth['redirect_uri']
                ?? ''
            )
        );

        if ($redirectUri === '') {
            throw new ChannelConnectionException(
                'OAUTH_REDIRECT_MISSING',
                'The YouTube OAuth redirect URI could not be verified.'
            );
        }

        $credentials = $this->credentials(
            $connection
        );

        $clientId = trim(
            (string) (
                $credentials['client_id']
                ?? ''
            )
        );

        $clientSecret = trim(
            (string) (
                $credentials['client_secret']
                ?? ''
            )
        );

        $tokenData = $this->exchangeCode(
            $clientId,
            $clientSecret,
            $code,
            $redirectUri
        );

        $accessToken = trim(
            (string) (
                $tokenData['access_token']
                ?? ''
            )
        );

        if ($accessToken === '') {
            throw new ChannelConnectionException(
                'OAUTH_ACCESS_TOKEN_MISSING',
                'Google OAuth did not return a usable YouTube access token.'
            );
        }

        $refreshToken = trim(
            (string) (
                $tokenData['refresh_token']
                ?? (
                    $credentials['refresh_token']
                    ?? ''
                )
            )
        );

        if ($refreshToken === '') {
            throw new ChannelConnectionException(
                'OAUTH_REFRESH_TOKEN_MISSING',
                'Google did not return an offline refresh token. Reconnect YouTube and approve the consent screen.'
            );
        }

        $channel = $this->loadChannel(
            $accessToken
        );

        return DB::transaction(
            function () use (
                $connection,
                $admin,
                $request,
                $credentials,
                $refreshToken,
                $channel,
                $tokenData
            ): ChannelConnection {
                $locked = ChannelConnection::query()
                    ->whereKey(
                        $connection->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $before = $this->safeSnapshot(
                    $locked
                );

                $locked->forceFill([
                    'credentials' => [
                        'client_id' =>
                            $credentials['client_id'],
                        'client_secret' =>
                            $credentials['client_secret'],
                        'refresh_token' =>
                            $refreshToken,
                    ],
                    'metadata' => [
                        'channel_id' =>
                            $channel['channel_id'],
                        'channel_title' =>
                            $channel['channel_title'],
                        'channel_handle' =>
                            $channel['channel_handle'],
                        'thumbnail_url' =>
                            $channel['thumbnail_url'],
                        'scopes' =>
                            $this->scopeList(
                                $tokenData['scope']
                                ?? null
                            ),
                    ],
                    'is_enabled' => true,
                    'last_test_status' =>
                        ChannelConnection::TEST_SUCCESS,
                    'last_test_message' =>
                        'YouTube OAuth and channel ownership verified.',
                    'last_tested_at' => now(),
                    'version' =>
                        $locked->version + 1,
                    'updated_by_admin_id' =>
                        $admin->getKey(),
                ])->save();

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->getKey(),
                    action: 'CHANNEL_YOUTUBE_OAUTH_CONNECTED',
                    module: 'OUR_CHANNEL',
                    targetType: 'CHANNEL_CONNECTION',
                    targetId: $locked->getKey(),
                    oldValues: $before,
                    newValues: $this->safeSnapshot(
                        $locked
                    ),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                return $locked->fresh();
            },
            3
        );
    }

    private function exchangeCode(
        string $clientId,
        string $clientSecret,
        string $code,
        string $redirectUri
    ): array {
        $tokenUrl = (string) config(
            'tradex_channels.platforms.youtube.token_url',
            'https://oauth2.googleapis.com/token'
        );

        try {
            $response = Http::connectTimeout(10)
                ->timeout(30)
                ->acceptJson()
                ->asForm()
                ->post(
                    $tokenUrl,
                    [
                        'client_id' => $clientId,
                        'client_secret' => $clientSecret,
                        'code' => $code,
                        'grant_type' =>
                            'authorization_code',
                        'redirect_uri' =>
                            $redirectUri,
                    ]
                );
        } catch (ConnectionException) {
            throw new ChannelConnectionException(
                'GOOGLE_TOKEN_NETWORK_ERROR',
                'TradeX could not reach Google OAuth to exchange the authorization code.'
            );
        }

        $json = $response->json();

        if (
            ! $response->successful()
            || ! is_array($json)
        ) {
            $code = is_array($json)
                ? (
                    $json['error']
                    ?? $response->status()
                )
                : $response->status();

            throw new ChannelConnectionException(
                (string) $code,
                'Google rejected the YouTube OAuth token exchange. Verify the OAuth client and redirect URI.'
            );
        }

        return $json;
    }

    private function loadChannel(
        string $accessToken
    ): array {
        $url = (string) config(
            'tradex_channels.platforms.youtube.channels_url',
            'https://www.googleapis.com/youtube/v3/channels'
        );

        try {
            $response = Http::connectTimeout(10)
                ->timeout(30)
                ->withToken($accessToken)
                ->acceptJson()
                ->get(
                    $url,
                    [
                        'part' => 'snippet',
                        'mine' => 'true',
                        'maxResults' => 1,
                    ]
                );
        } catch (ConnectionException) {
            throw new ChannelConnectionException(
                'YOUTUBE_API_NETWORK_ERROR',
                'TradeX could not reach the YouTube Data API to verify the connected channel.'
            );
        }

        $json = $response->json();

        if (
            ! $response->successful()
            || ! is_array($json)
        ) {
            throw new ChannelConnectionException(
                'YOUTUBE_CHANNEL_LOOKUP_FAILED',
                'YouTube could not verify the authenticated channel. Confirm the YouTube Data API is enabled for this Google Cloud project.'
            );
        }

        $item = $json['items'][0]
            ?? null;

        if (! is_array($item)) {
            throw new ChannelConnectionException(
                'YOUTUBE_CHANNEL_MISSING',
                'The authorized Google account does not expose a YouTube channel.'
            );
        }

        $snippet = is_array(
            $item['snippet']
            ?? null
        )
            ? $item['snippet']
            : [];

        $thumbnails = is_array(
            $snippet['thumbnails']
            ?? null
        )
            ? $snippet['thumbnails']
            : [];

        return [
            'channel_id' => trim(
                (string) (
                    $item['id']
                    ?? ''
                )
            ),
            'channel_title' => trim(
                (string) (
                    $snippet['title']
                    ?? ''
                )
            ),
            'channel_handle' => trim(
                (string) (
                    $snippet['customUrl']
                    ?? ''
                )
            ) ?: null,
            'thumbnail_url' =>
                $thumbnails['default']['url']
                ?? $thumbnails['medium']['url']
                ?? null,
        ];
    }

    private function credentials(
        ChannelConnection $connection
    ): array {
        return is_array($connection->credentials)
            ? $connection->credentials
            : [];
    }

    private function scopeList(mixed $scope): array
    {
        if (! is_string($scope)) {
            return [];
        }

        return array_values(
            array_filter(
                preg_split(
                    '/\s+/',
                    trim($scope)
                )
                ?: []
            )
        );
    }

    private function safeSnapshot(
        ChannelConnection $connection
    ): array {
        $credentials = $this->credentials(
            $connection
        );

        $metadata = is_array(
            $connection->metadata
        )
            ? $connection->metadata
            : [];

        return [
            'platform' => $connection->platform,
            'is_enabled' =>
                (bool) $connection->is_enabled,
            'client_id_present' =>
                filled(
                    $credentials['client_id']
                    ?? null
                ),
            'client_secret_present' =>
                filled(
                    $credentials['client_secret']
                    ?? null
                ),
            'refresh_token_present' =>
                filled(
                    $credentials['refresh_token']
                    ?? null
                ),
            'channel_id' =>
                $metadata['channel_id']
                ?? null,
            'channel_title' =>
                $metadata['channel_title']
                ?? null,
            'last_test_status' =>
                $connection->last_test_status,
            'last_tested_at' =>
                $connection->last_tested_at
                    ?->toISOString(),
            'version' =>
                (int) $connection->version,
        ];
    }
}
