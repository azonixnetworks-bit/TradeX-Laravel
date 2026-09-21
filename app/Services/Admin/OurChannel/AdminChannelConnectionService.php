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
                'bot_token' =>
                    (string) $result['_validation_error'],
            ]);
        }

        return $result;
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
