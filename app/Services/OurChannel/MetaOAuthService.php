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

class MetaOAuthService
{
    private const SESSION_KEY =
        'our_channel.meta_oauth';

    private const REQUIRED_SCOPES = [
        'pages_show_list',
        'pages_read_engagement',
        'pages_manage_posts',
        'instagram_basic',
        'instagram_content_publish',
    ];

    public function authorizationUrl(
        ChannelConnection $connection,
        AdminUser $admin,
        Request $request,
        string $redirectUri
    ): string {
        $credentials = $this->credentials(
            $connection
        );

        $appId = trim(
            (string) (
                $credentials['app_id']
                ?? ''
            )
        );

        $appSecret = trim(
            (string) (
                $credentials['app_secret']
                ?? ''
            )
        );

        if ($appId === '' || $appSecret === '') {
            throw new ChannelConnectionException(
                'META_APP_MISSING',
                'Save the Meta App ID and App Secret before connecting Facebook and Instagram.'
            );
        }

        $version = $this->graphVersion(
            $connection
        );

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

        return 'https://www.facebook.com/'
            .$version
            .'/dialog/oauth?'
            .http_build_query(
                [
                    'client_id' => $appId,
                    'redirect_uri' => $redirectUri,
                    'response_type' => 'code',
                    'scope' => implode(
                        ',',
                        self::REQUIRED_SCOPES
                    ),
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

        $this->assertOAuthSession(
            $oauth,
            $connection,
            $admin,
            $request
        );

        $providerError = trim(
            (string) $request->query(
                'error',
                ''
            )
        );

        if ($providerError !== '') {
            throw new ChannelConnectionException(
                'META_OAUTH_'.$providerError,
                'Meta did not grant the requested Facebook and Instagram permissions.'
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
                'META_OAUTH_CODE_MISSING',
                'Meta OAuth did not return an authorization code.'
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
                'META_CONFIGURATION_CHANGED',
                'Meta connection settings changed during authorization. Start the connection again.'
            );
        }

        $redirectUri = trim(
            (string) (
                $oauth['redirect_uri']
                ?? ''
            )
        );

        $credentials = $this->credentials(
            $connection
        );

        $appId = trim(
            (string) (
                $credentials['app_id']
                ?? ''
            )
        );

        $appSecret = trim(
            (string) (
                $credentials['app_secret']
                ?? ''
            )
        );

        $version = $this->graphVersion(
            $connection
        );

        $shortToken = $this->exchangeCode(
            $version,
            $appId,
            $appSecret,
            $code,
            $redirectUri
        );

        $userToken = $this->longLivedToken(
            $version,
            $appId,
            $appSecret,
            $shortToken
        );

        $granted = $this->grantedPermissions(
            $version,
            $userToken
        );

        $missing = array_values(
            array_diff(
                self::REQUIRED_SCOPES,
                $granted
            )
        );

        if ($missing !== []) {
            throw new ChannelConnectionException(
                'META_PERMISSION_MISSING',
                'Meta did not grant every permission required for Facebook Page and Instagram publishing: '
                    .implode(', ', $missing)
                    .'.'
            );
        }

        $pages = $this->loadPages(
            $version,
            $userToken
        );

        if ($pages === []) {
            throw new ChannelConnectionException(
                'META_PAGE_MISSING',
                'No manageable Facebook Page was returned. Confirm the Facebook account has Page access and grant the requested permissions.'
            );
        }

        if (count($pages) === 1) {
            return $this->activatePage(
                $connection,
                $admin,
                $request,
                $pages[0],
                $granted
            );
        }

        return $this->storePendingPages(
            $connection,
            $admin,
            $request,
            $pages,
            $granted
        );
    }

    public function selectPage(
        ChannelConnection $connection,
        AdminUser $admin,
        Request $request,
        string $pageId,
        int $expectedVersion
    ): ChannelConnection {
        $connection->refresh();

        if (
            (int) $connection->version
            !== $expectedVersion
        ) {
            abort(
                409,
                'Meta connection settings changed in another session. Refresh and try again.'
            );
        }

        $credentials = $this->credentials(
            $connection
        );

        $candidates = is_array(
            $credentials['candidate_pages']
            ?? null
        )
            ? $credentials['candidate_pages']
            : [];

        $selected = null;

        foreach ($candidates as $candidate) {
            if (
                is_array($candidate)
                && hash_equals(
                    (string) (
                        $candidate['page_id']
                        ?? ''
                    ),
                    $pageId
                )
            ) {
                $selected = $candidate;
                break;
            }
        }

        if (! is_array($selected)) {
            throw new ChannelConnectionException(
                'META_PAGE_SELECTION_INVALID',
                'The selected Facebook Page is no longer available in this Meta OAuth session. Reconnect Meta.'
            );
        }

        $metadata = is_array(
            $connection->metadata
        )
            ? $connection->metadata
            : [];

        $granted = is_array(
            $metadata['granted_permissions']
            ?? null
        )
            ? $metadata['granted_permissions']
            : [];

        return $this->activatePage(
            $connection,
            $admin,
            $request,
            $selected,
            $granted
        );
    }

    private function activatePage(
        ChannelConnection $connection,
        AdminUser $admin,
        Request $request,
        array $page,
        array $granted
    ): ChannelConnection {
        $pageToken = trim(
            (string) (
                $page['page_access_token']
                ?? ''
            )
        );

        $pageId = trim(
            (string) (
                $page['page_id']
                ?? ''
            )
        );

        if ($pageToken === '' || $pageId === '') {
            throw new ChannelConnectionException(
                'META_PAGE_TOKEN_MISSING',
                'Meta did not return a usable Page access token.'
            );
        }

        $verified = $this->verifyPage(
            $this->graphVersion($connection),
            $pageId,
            $pageToken
        );

        $instagram = is_array(
            $verified['instagram']
            ?? null
        )
            ? $verified['instagram']
            : null;

        return DB::transaction(
            function () use (
                $connection,
                $admin,
                $request,
                $page,
                $pageToken,
                $pageId,
                $verified,
                $instagram,
                $granted
            ): ChannelConnection {
                $locked = ChannelConnection::query()
                    ->whereKey(
                        $connection->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $existing = $this->credentials(
                    $locked
                );

                $settings = is_array(
                    $locked->settings
                )
                    ? $locked->settings
                    : [];

                $before = $this->safeSnapshot(
                    $locked
                );

                $locked->forceFill([
                    'credentials' => [
                        'app_id' =>
                            $existing['app_id']
                            ?? null,
                        'app_secret' =>
                            $existing['app_secret']
                            ?? null,
                        'page_access_token' =>
                            $pageToken,
                    ],
                    'settings' => [
                        ...$settings,
                        'facebook_enabled' => true,
                        'instagram_enabled' =>
                            $instagram !== null,
                    ],
                    'metadata' => [
                        'page_id' => $pageId,
                        'page_name' =>
                            $verified['page_name']
                            ?? (
                                $page['page_name']
                                ?? null
                            ),
                        'instagram' => $instagram,
                        'granted_permissions' =>
                            array_values($granted),
                    ],
                    'is_enabled' => true,
                    'last_test_status' =>
                        ChannelConnection::TEST_SUCCESS,
                    'last_test_message' =>
                        $instagram !== null
                            ? 'Facebook Page and linked Instagram Professional account verified.'
                            : 'Facebook Page verified. No linked Instagram Professional account was found.',
                    'last_tested_at' => now(),
                    'version' =>
                        $locked->version + 1,
                    'updated_by_admin_id' =>
                        $admin->getKey(),
                ])->save();

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->getKey(),
                    action: 'CHANNEL_META_PAGE_CONNECTED',
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

    private function storePendingPages(
        ChannelConnection $connection,
        AdminUser $admin,
        Request $request,
        array $pages,
        array $granted
    ): ChannelConnection {
        return DB::transaction(
            function () use (
                $connection,
                $admin,
                $request,
                $pages,
                $granted
            ): ChannelConnection {
                $locked = ChannelConnection::query()
                    ->whereKey(
                        $connection->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $existing = $this->credentials(
                    $locked
                );

                $settings = is_array(
                    $locked->settings
                )
                    ? $locked->settings
                    : [];

                $before = $this->safeSnapshot(
                    $locked
                );

                $locked->forceFill([
                    'credentials' => [
                        'app_id' =>
                            $existing['app_id']
                            ?? null,
                        'app_secret' =>
                            $existing['app_secret']
                            ?? null,
                        'candidate_pages' =>
                            $pages,
                    ],
                    'settings' => [
                        ...$settings,
                        'facebook_enabled' => false,
                        'instagram_enabled' => false,
                    ],
                    'metadata' => [
                        'candidate_pages' =>
                            array_map(
                                static fn (array $page): array => [
                                    'page_id' =>
                                        $page['page_id']
                                        ?? null,
                                    'page_name' =>
                                        $page['page_name']
                                        ?? null,
                                    'instagram' =>
                                        $page['instagram']
                                        ?? null,
                                ],
                                $pages
                            ),
                        'granted_permissions' =>
                            array_values($granted),
                    ],
                    'is_enabled' => false,
                    'last_test_status' =>
                        ChannelConnection::TEST_UNTESTED,
                    'last_test_message' =>
                        'Multiple Facebook Pages are available. Select the Page TradeX should publish to.',
                    'last_tested_at' => now(),
                    'version' =>
                        $locked->version + 1,
                    'updated_by_admin_id' =>
                        $admin->getKey(),
                ])->save();

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->getKey(),
                    action: 'CHANNEL_META_PAGE_SELECTION_REQUIRED',
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

    private function assertOAuthSession(
        mixed $oauth,
        ChannelConnection $connection,
        AdminUser $admin,
        Request $request
    ): void {
        if (! is_array($oauth)) {
            throw new ChannelConnectionException(
                'META_OAUTH_SESSION_MISSING',
                'The Meta OAuth session expired. Start the connection again.'
            );
        }

        if (
            (int) ($oauth['admin_id'] ?? 0)
            !== (int) $admin->getKey()
        ) {
            throw new ChannelConnectionException(
                'META_OAUTH_ADMIN_MISMATCH',
                'The Meta OAuth session does not belong to this admin session.'
            );
        }

        if (
            (int) ($oauth['expires_at'] ?? 0)
            < now()->timestamp
        ) {
            throw new ChannelConnectionException(
                'META_OAUTH_SESSION_EXPIRED',
                'The Meta OAuth session expired. Start the connection again.'
            );
        }

        $state = trim(
            (string) $request->query(
                'state',
                ''
            )
        );

        $stateHash = trim(
            (string) (
                $oauth['state_hash']
                ?? ''
            )
        );

        if (
            $state === ''
            || $stateHash === ''
            || ! hash_equals(
                $stateHash,
                hash('sha256', $state)
            )
        ) {
            throw new ChannelConnectionException(
                'META_OAUTH_STATE_INVALID',
                'Meta OAuth state verification failed. Start the connection again.'
            );
        }
    }

    private function exchangeCode(
        string $version,
        string $appId,
        string $appSecret,
        string $code,
        string $redirectUri
    ): string {
        $response = $this->request(
            'GET',
            $this->graphBase()
                .'/'
                .$version
                .'/oauth/access_token',
            [
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'redirect_uri' => $redirectUri,
                'code' => $code,
            ],
            'META_TOKEN_EXCHANGE_FAILED',
            'Meta rejected the OAuth authorization code.'
        );

        $token = trim(
            (string) (
                $response['access_token']
                ?? ''
            )
        );

        if ($token === '') {
            throw new ChannelConnectionException(
                'META_ACCESS_TOKEN_MISSING',
                'Meta OAuth did not return a usable access token.'
            );
        }

        return $token;
    }

    private function longLivedToken(
        string $version,
        string $appId,
        string $appSecret,
        string $shortToken
    ): string {
        $response = $this->request(
            'GET',
            $this->graphBase()
                .'/'
                .$version
                .'/oauth/access_token',
            [
                'grant_type' =>
                    'fb_exchange_token',
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'fb_exchange_token' =>
                    $shortToken,
            ],
            'META_LONG_LIVED_TOKEN_FAILED',
            'Meta could not exchange the login token for a long-lived token.'
        );

        $token = trim(
            (string) (
                $response['access_token']
                ?? ''
            )
        );

        if ($token === '') {
            throw new ChannelConnectionException(
                'META_LONG_LIVED_TOKEN_MISSING',
                'Meta did not return a long-lived user access token.'
            );
        }

        return $token;
    }

    private function grantedPermissions(
        string $version,
        string $userToken
    ): array {
        $response = $this->request(
            'GET',
            $this->graphBase()
                .'/'
                .$version
                .'/me/permissions',
            [
                'access_token' => $userToken,
            ],
            'META_PERMISSION_LOOKUP_FAILED',
            'TradeX could not verify the permissions granted to the Meta app.'
        );

        $granted = [];

        foreach (
            is_array($response['data'] ?? null)
                ? $response['data']
                : []
            as $permission
        ) {
            if (
                is_array($permission)
                && (
                    $permission['status']
                    ?? null
                ) === 'granted'
            ) {
                $name = trim(
                    (string) (
                        $permission['permission']
                        ?? ''
                    )
                );

                if ($name !== '') {
                    $granted[] = $name;
                }
            }
        }

        return array_values(
            array_unique($granted)
        );
    }

    private function loadPages(
        string $version,
        string $userToken
    ): array {
        $response = $this->request(
            'GET',
            $this->graphBase()
                .'/'
                .$version
                .'/me/accounts',
            [
                'fields' =>
                    'id,name,access_token,instagram_business_account{id,username,name,profile_picture_url}',
                'limit' => 100,
                'access_token' => $userToken,
            ],
            'META_PAGE_LOOKUP_FAILED',
            'TradeX could not load Facebook Pages available to this Meta account.'
        );

        $pages = [];

        foreach (
            is_array($response['data'] ?? null)
                ? $response['data']
                : []
            as $page
        ) {
            if (! is_array($page)) {
                continue;
            }

            $pageId = trim(
                (string) (
                    $page['id']
                    ?? ''
                )
            );

            $pageToken = trim(
                (string) (
                    $page['access_token']
                    ?? ''
                )
            );

            if ($pageId === '' || $pageToken === '') {
                continue;
            }

            $instagram = is_array(
                $page['instagram_business_account']
                ?? null
            )
                ? $this->safeInstagram(
                    $page['instagram_business_account']
                )
                : null;

            if (
                is_array($instagram)
                && trim(
                    (string) (
                        $instagram['id']
                        ?? ''
                    )
                ) === ''
            ) {
                $instagram = null;
            }

            $pages[] = [
                'page_id' => $pageId,
                'page_name' => trim(
                    (string) (
                        $page['name']
                        ?? 'Facebook Page'
                    )
                ),
                'page_access_token' =>
                    $pageToken,
                'instagram' => $instagram,
            ];
        }

        return $pages;
    }

    private function verifyPage(
        string $version,
        string $pageId,
        string $pageToken
    ): array {
        $response = $this->request(
            'GET',
            $this->graphBase()
                .'/'
                .$version
                .'/'
                .$pageId,
            [
                'fields' =>
                    'id,name,instagram_business_account{id,username,name,profile_picture_url}',
                'access_token' => $pageToken,
            ],
            'META_PAGE_VERIFY_FAILED',
            'Meta could not verify the selected Facebook Page access token.'
        );

        if (
            trim(
                (string) (
                    $response['id']
                    ?? ''
                )
            ) !== $pageId
        ) {
            throw new ChannelConnectionException(
                'META_PAGE_ID_MISMATCH',
                'Meta returned a different Facebook Page than the selected Page.'
            );
        }

        $instagram = is_array(
            $response['instagram_business_account']
            ?? null
        )
            ? $this->safeInstagram(
                $response['instagram_business_account']
            )
            : null;

        if (
            is_array($instagram)
            && trim(
                (string) (
                    $instagram['id']
                    ?? ''
                )
            ) === ''
        ) {
            $instagram = null;
        }

        return [
            'page_name' => trim(
                (string) (
                    $response['name']
                    ?? ''
                )
            ) ?: null,
            'instagram' => $instagram,
        ];
    }

    private function request(
        string $method,
        string $url,
        array $query,
        string $code,
        string $message
    ): array {
        try {
            $response = Http::connectTimeout(10)
                ->timeout(45)
                ->acceptJson()
                ->send(
                    $method,
                    $url,
                    [
                        'query' => $query,
                    ]
                );
        } catch (ConnectionException) {
            throw new ChannelConnectionException(
                'META_NETWORK_ERROR',
                'TradeX could not reach the Meta Graph API.'
            );
        }

        $json = $response->json();

        if (
            ! $response->successful()
            || ! is_array($json)
        ) {
            $providerCode = is_array($json)
                ? (
                    $json['error']['code']
                    ?? $json['error']['type']
                    ?? $code
                )
                : $code;

            throw new ChannelConnectionException(
                (string) $providerCode,
                $message
            );
        }

        return $json;
    }

    private function safeInstagram(
        array $instagram
    ): array {
        return [
            'id' => trim(
                (string) (
                    $instagram['id']
                    ?? ''
                )
            ),
            'username' => trim(
                (string) (
                    $instagram['username']
                    ?? ''
                )
            ) ?: null,
            'name' => trim(
                (string) (
                    $instagram['name']
                    ?? ''
                )
            ) ?: null,
            'profile_picture_url' =>
                is_string(
                    $instagram['profile_picture_url']
                    ?? null
                )
                    ? $instagram[
                        'profile_picture_url'
                    ]
                    : null,
        ];
    }

    private function graphVersion(
        ChannelConnection $connection
    ): string {
        $settings = is_array(
            $connection->settings
        )
            ? $connection->settings
            : [];

        $version = strtolower(
            trim(
                (string) (
                    $settings['graph_version']
                    ?? config(
                        'tradex_channels.platforms.facebook.graph_version',
                        'v26.0'
                    )
                )
            )
        );

        return preg_match(
            '/^v\d{1,2}\.0$/',
            $version
        ) === 1
            ? $version
            : 'v26.0';
    }

    private function graphBase(): string
    {
        return rtrim(
            (string) config(
                'tradex_channels.platforms.facebook.graph_base_url',
                'https://graph.facebook.com'
            ),
            '/'
        );
    }

    private function credentials(
        ChannelConnection $connection
    ): array {
        return is_array($connection->credentials)
            ? $connection->credentials
            : [];
    }

    private function safeSnapshot(
        ChannelConnection $connection
    ): array {
        $credentials = $this->credentials(
            $connection
        );

        $settings = is_array(
            $connection->settings
        )
            ? $connection->settings
            : [];

        $metadata = is_array(
            $connection->metadata
        )
            ? $connection->metadata
            : [];

        return [
            'platform' => $connection->platform,
            'is_enabled' =>
                (bool) $connection->is_enabled,
            'app_id_present' =>
                filled(
                    $credentials['app_id']
                    ?? null
                ),
            'app_secret_present' =>
                filled(
                    $credentials['app_secret']
                    ?? null
                ),
            'page_access_token_present' =>
                filled(
                    $credentials['page_access_token']
                    ?? null
                ),
            'pending_page_count' =>
                is_array(
                    $credentials['candidate_pages']
                    ?? null
                )
                    ? count(
                        $credentials['candidate_pages']
                    )
                    : 0,
            'graph_version' =>
                $settings['graph_version']
                ?? null,
            'facebook_enabled' =>
                (bool) (
                    $settings['facebook_enabled']
                    ?? false
                ),
            'instagram_enabled' =>
                (bool) (
                    $settings['instagram_enabled']
                    ?? false
                ),
            'page_id' =>
                $metadata['page_id']
                ?? null,
            'page_name' =>
                $metadata['page_name']
                ?? null,
            'instagram_id' =>
                $metadata['instagram']['id']
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
