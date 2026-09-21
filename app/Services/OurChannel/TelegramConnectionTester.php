<?php

namespace App\Services\OurChannel;

use App\Exceptions\ChannelConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class TelegramConnectionTester
{
    public function test(
        string $botToken,
        string $chatId
    ): array {
        $base = rtrim(
            (string) config(
                'tradex_channels.platforms.telegram.api_base_url',
                'https://api.telegram.org'
            ),
            '/'
        );

        $timeout = max(
            5,
            min(
                30,
                (int) config(
                    'tradex_channels.platforms.telegram.timeout_seconds',
                    20
                )
            )
        );

        try {
            $bot = $this->telegramCall(
                $base,
                $botToken,
                'getMe',
                [],
                $timeout
            );

            $botId = $bot['id'] ?? null;

            if (! is_int($botId) && ! ctype_digit((string) $botId)) {
                throw new ChannelConnectionException(
                    'BOT_ID_MISSING',
                    'Telegram authenticated the token but did not return a valid bot identifier.'
                );
            }

            $chat = $this->telegramCall(
                $base,
                $botToken,
                'getChat',
                [
                    'chat_id' => $chatId,
                ],
                $timeout
            );

            if (($chat['type'] ?? null) !== 'channel') {
                throw new ChannelConnectionException(
                    'DESTINATION_NOT_CHANNEL',
                    'The configured Telegram destination is not a Channel.'
                );
            }

            $member = $this->telegramCall(
                $base,
                $botToken,
                'getChatMember',
                [
                    'chat_id' => $chatId,
                    'user_id' => (string) $botId,
                ],
                $timeout
            );

            $memberStatus = strtolower(
                trim(
                    (string) (
                        $member['status']
                        ?? ''
                    )
                )
            );

            $isAdministrator = in_array(
                $memberStatus,
                [
                    'administrator',
                    'creator',
                ],
                true
            );

            $canPost = $memberStatus === 'creator'
                || (
                    $memberStatus === 'administrator'
                    && (
                        ($member['can_post_messages'] ?? false)
                        === true
                    )
                );

            if (! $isAdministrator || ! $canPost) {
                throw new ChannelConnectionException(
                    'POST_PERMISSION_MISSING',
                    'The Telegram bot must be a Channel administrator with permission to post messages.'
                );
            }

            return [
                'bot_id' => (string) $botId,
                'bot_username' => $this->nullableText(
                    $bot['username']
                    ?? null
                ),
                'bot_display_name' => $this->nullableText(
                    $bot['first_name']
                    ?? null
                ),
                'channel_id' => isset($chat['id'])
                    ? (string) $chat['id']
                    : null,
                'channel_title' => $this->nullableText(
                    $chat['title']
                    ?? null
                ),
                'channel_username' => $this->nullableText(
                    $chat['username']
                    ?? null
                ),
                'member_status' => $memberStatus,
                'can_post_messages' => true,
            ];
        } catch (ChannelConnectionException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new ChannelConnectionException(
                'NETWORK_ERROR',
                'TradeX could not safely verify the Telegram connection.'
            );
        }
    }

    private function telegramCall(
        string $base,
        string $botToken,
        string $method,
        array $payload,
        int $timeout
    ): array {
        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->asForm()
                ->post(
                    $base.'/bot'.$botToken.'/'.$method,
                    $payload
                );
        } catch (Throwable) {
            throw new ChannelConnectionException(
                'NETWORK_ERROR',
                'TradeX could not reach Telegram for connection verification.'
            );
        }

        return $this->parseResponse(
            $response,
            $method
        );
    }

    private function parseResponse(
        Response $response,
        string $method
    ): array {
        $json = $response->json();

        if (
            ! $response->successful()
            || ! is_array($json)
            || ($json['ok'] ?? false) !== true
            || ! is_array($json['result'] ?? null)
        ) {
            $code = is_array($json)
                ? (string) (
                    $json['error_code']
                    ?? $response->status()
                )
                : (string) $response->status();

            $message = match ($method) {
                'getMe' =>
                    'Telegram rejected the bot token.',

                'getChat' =>
                    'Telegram could not access the configured Channel. Add the bot to the Channel and verify the Channel ID or @username.',

                'getChatMember' =>
                    'Telegram could not verify the bot Channel permissions.',

                default =>
                    'Telegram rejected the connection verification request.',
            };

            throw new ChannelConnectionException(
                $code,
                $message
            );
        }

        return $json['result'];
    }

    private function nullableText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== ''
            ? $value
            : null;
    }
}
