<?php

namespace App\Services\Admin\OurChannel;

use App\Exceptions\ChannelConnectionException;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\ChannelConnection;
use App\Services\OurChannel\ChannelConnectionResolver;
use App\Services\OurChannel\TelegramConnectionTester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminChannelConnectionService
{
    public function __construct(
        private readonly ChannelConnectionResolver $resolver,
        private readonly TelegramConnectionTester $telegramTester
    ) {
    }

    public function index(): array
    {
        return [
            'connections' =>
                $this->resolver->connectionOverview(),
        ];
    }



    public function metaConnection(): ChannelConnection
    {
        ChannelConnection::query()
            ->insertOrIgnore([
                'platform' =>
                    ChannelConnection::PLATFORM_META,
                'is_enabled' => false,
                'credentials' => null,
                'settings' => json_encode(
                    [
                        'graph_version' => 'v26.0',
                        'facebook_enabled' => false,
                        'instagram_enabled' => false,
                    ],
                    JSON_THROW_ON_ERROR
                ),
                'metadata' => json_encode(
                    [],
                    JSON_THROW_ON_ERROR
                ),
                'last_test_status' =>
                    ChannelConnection::TEST_UNTESTED,
                'version' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return ChannelConnection::query()
            ->where(
                'platform',
                ChannelConnection::PLATFORM_META
            )
            ->firstOrFail();
    }

    public function updateMeta(
        AdminUser $admin,
        array $data,
        Request $request
    ): array {
        return DB::transaction(
            function () use (
                $admin,
                $data,
                $request
            ): array {
                $this->metaConnection();

                $connection = ChannelConnection::query()
                    ->where(
                        'platform',
                        ChannelConnection::PLATFORM_META
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    (int) $data['version']
                    !== (int) $connection->version
                ) {
                    abort(
                        409,
                        'Meta connection settings changed in another session. Refresh and try again.'
                    );
                }

                $existing = is_array(
                    $connection->credentials
                )
                    ? $connection->credentials
                    : [];

                $submittedAppId = trim(
                    (string) (
                        $data['app_id']
                        ?? ''
                    )
                );

                $submittedSecret = trim(
                    (string) (
                        $data['app_secret']
                        ?? ''
                    )
                );

                $appId = $submittedAppId !== ''
                    ? $submittedAppId
                    : trim(
                        (string) (
                            $existing['app_id']
                            ?? ''
                        )
                    );

                $appSecret = $submittedSecret !== ''
                    ? $submittedSecret
                    : trim(
                        (string) (
                            $existing['app_secret']
                            ?? ''
                        )
                    );

                if ($appId === '') {
                    throw ValidationException::withMessages([
                        'app_id' =>
                            'Enter the Meta App ID before saving the connection.',
                    ]);
                }

                if ($appSecret === '') {
                    throw ValidationException::withMessages([
                        'app_secret' =>
                            'Enter the Meta App Secret before saving the connection.',
                    ]);
                }

                $credentialsChanged =
                    $appId
                        !== trim(
                            (string) (
                                $existing['app_id']
                                ?? ''
                            )
                        )
                    || $appSecret
                        !== trim(
                            (string) (
                                $existing['app_secret']
                                ?? ''
                            )
                        );

                $metadata = $credentialsChanged
                    ? []
                    : (
                        is_array($connection->metadata)
                            ? $connection->metadata
                            : []
                    );

                $credentials = [
                    'app_id' => $appId,
                    'app_secret' => $appSecret,
                    'page_access_token' =>
                        $credentialsChanged
                            ? null
                            : (
                                $existing['page_access_token']
                                ?? null
                            ),
                    'candidate_pages' =>
                        $credentialsChanged
                            ? null
                            : (
                                $existing['candidate_pages']
                                ?? null
                            ),
                ];

                $testStatus = $credentialsChanged
                    ? ChannelConnection::TEST_UNTESTED
                    : $connection->last_test_status;

                $testMessage = $credentialsChanged
                    ? 'Meta App credentials changed. Connect Meta again.'
                    : $connection->last_test_message;

                $lastTestedAt = $credentialsChanged
                    ? null
                    : $connection->last_tested_at;

                $facebookEnabled = (bool) (
                    $data['facebook_enabled']
                    ?? false
                );

                $instagramEnabled = (bool) (
                    $data['instagram_enabled']
                    ?? false
                );

                $verified =
                    $testStatus
                        === ChannelConnection::TEST_SUCCESS
                    && filled(
                        $credentials['page_access_token']
                        ?? null
                    );

                if (
                    ($facebookEnabled || $instagramEnabled)
                    && ! $verified
                ) {
                    throw ValidationException::withMessages([
                        'facebook_enabled' =>
                            'Connect and verify a Facebook Page before enabling Meta auto publishing.',
                    ]);
                }

                $instagramId = trim(
                    (string) (
                        $metadata['instagram']['id']
                        ?? ''
                    )
                );

                if (
                    $instagramEnabled
                    && $instagramId === ''
                ) {
                    throw ValidationException::withMessages([
                        'instagram_enabled' =>
                            'The selected Facebook Page does not have a linked Instagram Professional account.',
                    ]);
                }

                $before = $this->safeMetaSnapshot(
                    $connection
                );

                $connection->forceFill([
                    'credentials' => $credentials,
                    'settings' => [
                        'graph_version' =>
                            $data['graph_version']
                            ?? 'v26.0',
                        'facebook_enabled' =>
                            $facebookEnabled,
                        'instagram_enabled' =>
                            $instagramEnabled,
                    ],
                    'metadata' => $metadata,
                    'is_enabled' =>
                        $facebookEnabled
                        || $instagramEnabled,
                    'last_test_status' => $testStatus,
                    'last_test_message' => $testMessage,
                    'last_tested_at' => $lastTestedAt,
                    'version' =>
                        $connection->version + 1,
                    'updated_by_admin_id' =>
                        $admin->getKey(),
                ])->save();

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->getKey(),
                    action: 'CHANNEL_META_CONNECTION_UPDATED',
                    module: 'OUR_CHANNEL',
                    targetType: 'CHANNEL_CONNECTION',
                    targetId: $connection->getKey(),
                    oldValues: $before,
                    newValues: $this->safeMetaSnapshot(
                        $connection
                    ),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                return $this->resolver
                    ->connectionOverview()['meta'];
            },
            3
        );
    }

    public function youtubeConnection(): ChannelConnection
    {
        ChannelConnection::query()
            ->insertOrIgnore([
                'platform' =>
                    ChannelConnection::PLATFORM_YOUTUBE,
                'is_enabled' => false,
                'credentials' => null,
                'settings' => json_encode(
                    [
                        'privacy_status' => 'private',
                        'category_id' => '27',
                    ],
                    JSON_THROW_ON_ERROR
                ),
                'metadata' => json_encode(
                    [],
                    JSON_THROW_ON_ERROR
                ),
                'last_test_status' =>
                    ChannelConnection::TEST_UNTESTED,
                'version' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return ChannelConnection::query()
            ->where(
                'platform',
                ChannelConnection::PLATFORM_YOUTUBE
            )
            ->firstOrFail();
    }

    public function updateYouTube(
        AdminUser $admin,
        array $data,
        Request $request
    ): array {
        return DB::transaction(
            function () use (
                $admin,
                $data,
                $request
            ): array {
                $this->youtubeConnection();

                $connection = ChannelConnection::query()
                    ->where(
                        'platform',
                        ChannelConnection::PLATFORM_YOUTUBE
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    (int) $data['version']
                    !== (int) $connection->version
                ) {
                    abort(
                        409,
                        'YouTube connection settings changed in another session. Refresh and try again.'
                    );
                }

                $existing = is_array(
                    $connection->credentials
                )
                    ? $connection->credentials
                    : [];

                $submittedClientId = trim(
                    (string) (
                        $data['client_id']
                        ?? ''
                    )
                );

                $submittedClientSecret = trim(
                    (string) (
                        $data['client_secret']
                        ?? ''
                    )
                );

                $clientId = $submittedClientId !== ''
                    ? $submittedClientId
                    : trim(
                        (string) (
                            $existing['client_id']
                            ?? ''
                        )
                    );

                $clientSecret = $submittedClientSecret !== ''
                    ? $submittedClientSecret
                    : trim(
                        (string) (
                            $existing['client_secret']
                            ?? ''
                        )
                    );

                if (
                    $clientId === ''
                    || ! str_ends_with(
                        strtolower($clientId),
                        '.apps.googleusercontent.com'
                    )
                ) {
                    throw ValidationException::withMessages([
                        'client_id' =>
                            'Enter a valid Google OAuth Web application Client ID ending in .apps.googleusercontent.com.',
                    ]);
                }

                if ($clientSecret === '') {
                    throw ValidationException::withMessages([
                        'client_secret' =>
                            'Enter the Google OAuth Client Secret.',
                    ]);
                }

                $credentialsChanged =
                    $clientId
                        !== trim(
                            (string) (
                                $existing['client_id']
                                ?? ''
                            )
                        )
                    || $clientSecret
                        !== trim(
                            (string) (
                                $existing['client_secret']
                                ?? ''
                            )
                        );

                $credentials = [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' =>
                        $credentialsChanged
                            ? null
                            : (
                                $existing['refresh_token']
                                ?? null
                            ),
                ];

                $metadata = $credentialsChanged
                    ? []
                    : (
                        is_array($connection->metadata)
                            ? $connection->metadata
                            : []
                    );

                $testStatus = $credentialsChanged
                    ? ChannelConnection::TEST_UNTESTED
                    : $connection->last_test_status;

                $testMessage = $credentialsChanged
                    ? 'Google OAuth client changed. Connect YouTube again.'
                    : $connection->last_test_message;

                $lastTestedAt = $credentialsChanged
                    ? null
                    : $connection->last_tested_at;

                $enable = (bool) (
                    $data['is_enabled']
                    ?? false
                );

                if (
                    $enable
                    && (
                        $testStatus
                            !== ChannelConnection::TEST_SUCCESS
                        || ! filled(
                            $credentials['refresh_token']
                            ?? null
                        )
                    )
                ) {
                    throw ValidationException::withMessages([
                        'is_enabled' =>
                            'Connect and verify the YouTube channel before enabling automatic publishing.',
                    ]);
                }

                $before = $this->safeYouTubeSnapshot(
                    $connection
                );

                $connection->forceFill([
                    'credentials' => $credentials,
                    'settings' => [
                        'privacy_status' =>
                            $data['privacy_status']
                            ?? 'private',
                        'category_id' =>
                            (string) (
                                $data['category_id']
                                ?? '27'
                            ),
                    ],
                    'metadata' => $metadata,
                    'is_enabled' => $enable,
                    'last_test_status' => $testStatus,
                    'last_test_message' => $testMessage,
                    'last_tested_at' => $lastTestedAt,
                    'version' =>
                        $connection->version + 1,
                    'updated_by_admin_id' =>
                        $admin->getKey(),
                ])->save();

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->getKey(),
                    action: 'CHANNEL_YOUTUBE_CONNECTION_UPDATED',
                    module: 'OUR_CHANNEL',
                    targetType: 'CHANNEL_CONNECTION',
                    targetId: $connection->getKey(),
                    oldValues: $before,
                    newValues: $this->safeYouTubeSnapshot(
                        $connection
                    ),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                return $this->resolver
                    ->connectionOverview()['youtube'];
            },
            3
        );
    }

    public function updateTelegram(
        AdminUser $admin,
        array $data,
        Request $request
    ): array {
        $result = DB::transaction(
            function () use (
                $admin,
                $data,
                $request
            ): array {
                ChannelConnection::query()
                    ->insertOrIgnore([
                        'platform' =>
                            ChannelConnection::PLATFORM_TELEGRAM,
                        'is_enabled' => false,
                        'credentials' => null,
                        'settings' => json_encode(
                            [
                                'protect_content' => false,
                            ],
                            JSON_THROW_ON_ERROR
                        ),
                        'metadata' => json_encode(
                            [],
                            JSON_THROW_ON_ERROR
                        ),
                        'last_test_status' =>
                            ChannelConnection::TEST_UNTESTED,
                        'version' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                $connection = ChannelConnection::query()
                    ->where(
                        'platform',
                        ChannelConnection::PLATFORM_TELEGRAM
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    (int) $data['version']
                    !== (int) $connection->version
                ) {
                    abort(
                        409,
                        'Channel connection settings changed in another session. Refresh and try again.'
                    );
                }

                $existing = is_array(
                    $connection->credentials
                )
                    ? $connection->credentials
                    : [];

                $submittedToken = trim(
                    (string) (
                        $data['bot_token']
                        ?? ''
                    )
                );

                $token = $submittedToken !== ''
                    ? $submittedToken
                    : trim(
                        (string) (
                            $existing['bot_token']
                            ?? ''
                        )
                    );

                $submittedChatId = trim(
                    (string) (
                        $data['chat_id']
                        ?? ''
                    )
                );

                $chatId = $submittedChatId !== ''
                    ? $submittedChatId
                    : trim(
                        (string) (
                            $existing['chat_id']
                            ?? ''
                        )
                    );

                if ($chatId === '') {
                    throw ValidationException::withMessages([
                        'chat_id' =>
                            'Enter the Telegram Channel ID or @username before saving this connection.',
                    ]);
                }

                if ($token === '') {
                    throw ValidationException::withMessages([
                        'bot_token' =>
                            'Enter the Telegram bot token before saving this connection.',
                    ]);
                }

                $fingerprint = $this->fingerprint(
                    $token,
                    $chatId
                );

                $oldFingerprint = is_array(
                    $connection->metadata
                )
                    ? (
                        $connection->metadata[
                            'credential_fingerprint'
                        ]
                        ?? null
                    )
                    : null;

                $credentialsChanged =
                    ! is_string($oldFingerprint)
                    || ! hash_equals(
                        $oldFingerprint,
                        $fingerprint
                    );

                $before = $this->safeSnapshot(
                    $connection
                );

                $testStatus = $connection->last_test_status;
                $testMessage = $connection->last_test_message;
                $lastTestedAt = $connection->last_tested_at;
                $metadata = is_array($connection->metadata)
                    ? $connection->metadata
                    : [];

                if (
                    $credentialsChanged
                    && $data['action'] !== 'SAVE_TEST'
                ) {
                    $testStatus =
                        ChannelConnection::TEST_UNTESTED;
                    $testMessage =
                        'Connection details changed. Run Test Connection before enabling auto publishing.';
                    $lastTestedAt = null;
                    $metadata = [];
                }

                if ($data['action'] === 'SAVE_TEST') {
                    try {
                        $verified = $this->telegramTester->test(
                            $token,
                            $chatId
                        );
                    } catch (ChannelConnectionException $exception) {
                        $connection->forceFill([
                            'credentials' => [
                                'bot_token' => $token,
                                'chat_id' => $chatId,
                            ],
                            'settings' => [
                                'protect_content' => (bool) (
                                    $data['protect_content']
                                    ?? false
                                ),
                            ],
                            'metadata' => [
                                'credential_fingerprint' =>
                                    $fingerprint,
                            ],
                            'is_enabled' => false,
                            'last_test_status' =>
                                ChannelConnection::TEST_FAILED,
                            'last_test_message' =>
                                '['
                                .$exception->providerCode
                                .'] '
                                .$exception->getMessage(),
                            'last_tested_at' => now(),
                            'version' =>
                                $connection->version + 1,
                            'updated_by_admin_id' =>
                                $admin->getKey(),
                        ])->save();

                        AuditLog::record(
                            actorType: 'ADMIN',
                            actorId: $admin->getKey(),
                            action: 'CHANNEL_TELEGRAM_CONNECTION_TEST_FAILED',
                            module: 'OUR_CHANNEL',
                            targetType: 'CHANNEL_CONNECTION',
                            targetId: $connection->getKey(),
                            oldValues: $before,
                            newValues: $this->safeSnapshot(
                                $connection
                            ),
                            ipAddress: $request->ip(),
                            userAgent: $request->userAgent()
                        );

                        return [
                            ...$this->resolver
                                ->connectionOverview()['telegram'],
                            '_validation_error' =>
                                $exception->getMessage(),
                        ];
                    }

                    $testStatus =
                        ChannelConnection::TEST_SUCCESS;
                    $testMessage =
                        'Telegram bot and Channel post permission verified.';
                    $lastTestedAt = now();
                    $metadata = [
                        ...$verified,
                        'credential_fingerprint' =>
                            $fingerprint,
                    ];
                }

                $enable = (bool) (
                    $data['is_enabled']
                    ?? false
                );

                if (
                    $enable
                    && (
                        $testStatus
                            !== ChannelConnection::TEST_SUCCESS
                        || ! isset(
                            $metadata[
                                'credential_fingerprint'
                            ]
                        )
                        || ! hash_equals(
                            (string) $metadata[
                                'credential_fingerprint'
                            ],
                            $fingerprint
                        )
                    )
                ) {
                    throw ValidationException::withMessages([
                        'is_enabled' =>
                            'Test this Telegram connection successfully before enabling automatic publishing.',
                    ]);
                }

                $connection->forceFill([
                    'credentials' => [
                        'bot_token' => $token,
                        'chat_id' => $chatId,
                    ],
                    'settings' => [
                        'protect_content' => (bool) (
                            $data['protect_content']
                            ?? false
                        ),
                    ],
                    'metadata' => $metadata,
                    'is_enabled' => $enable,
                    'last_test_status' => $testStatus,
                    'last_test_message' => $testMessage,
                    'last_tested_at' => $lastTestedAt,
                    'version' =>
                        $connection->version + 1,
                    'updated_by_admin_id' =>
                        $admin->getKey(),
                ])->save();

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->getKey(),
                    action: $data['action'] === 'SAVE_TEST'
                        ? 'CHANNEL_TELEGRAM_CONNECTION_TESTED'
                        : 'CHANNEL_TELEGRAM_CONNECTION_UPDATED',
                    module: 'OUR_CHANNEL',
                    targetType: 'CHANNEL_CONNECTION',
                    targetId: $connection->getKey(),
                    oldValues: $before,
                    newValues: $this->safeSnapshot(
                        $connection
                    ),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                return $this->resolver
                    ->connectionOverview()['telegram'];
            },
            3
        );

        if (isset($result['_validation_error'])) {
            throw ValidationException::withMessages([
                'connection_test' =>
                    (string) $result['_validation_error'],
            ]);
        }

        return $result;
    }



    private function safeMetaSnapshot(
        ChannelConnection $connection
    ): array {
        $credentials = is_array(
            $connection->credentials
        )
            ? $connection->credentials
            : [];

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
            'candidate_page_count' =>
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

    private function safeYouTubeSnapshot(
        ChannelConnection $connection
    ): array {
        $credentials = is_array(
            $connection->credentials
        )
            ? $connection->credentials
            : [];

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
            'privacy_status' =>
                $settings['privacy_status']
                ?? 'private',
            'category_id' =>
                $settings['category_id']
                ?? '27',
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

    private function fingerprint(
        string $token,
        string $chatId
    ): string {
        return hash_hmac(
            'sha256',
            $token."\0".$chatId,
            (string) config('app.key')
        );
    }

    private function safeSnapshot(
        ChannelConnection $connection
    ): array {
        $credentials = is_array(
            $connection->credentials
        )
            ? $connection->credentials
            : [];

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
            'token_present' =>
                trim(
                    (string) (
                        $credentials['bot_token']
                        ?? ''
                    )
                ) !== '',
            'destination_configured' =>
                trim(
                    (string) (
                        $credentials['chat_id']
                        ?? ''
                    )
                ) !== '',
            'protect_content' =>
                (bool) (
                    $settings['protect_content']
                    ?? false
                ),
            'last_test_status' =>
                $connection->last_test_status,
            'last_tested_at' =>
                $connection->last_tested_at
                    ?->toISOString(),
            'bot_username' =>
                $metadata['bot_username']
                ?? null,
            'channel_title' =>
                $metadata['channel_title']
                ?? null,
            'version' =>
                (int) $connection->version,
        ];
    }
}
