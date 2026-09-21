<?php

namespace App\Services\OurChannel;

use App\Exceptions\ChannelConnectionException;
use Illuminate\Http\Client\ConnectionException;
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
        } catch (ConnectionException $exception) {
            throw $this->transportFailure(
                $exception
            );
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
            $response = Http::connectTimeout(
                    min(10, $timeout)
                )
                ->timeout($timeout)
                ->acceptJson()
                ->asForm()
                ->post(
                    $base.'/bot'.$botToken.'/'.$method,
                    $payload
                );
        } catch (ConnectionException $exception) {
            throw $this->transportFailure(
                $exception
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

    private function transportFailure(
        ConnectionException $exception
    ): ChannelConnectionException {
        $message = strtolower(
            $exception->getMessage()
        );

        if (
            str_contains($message, 'certificate')
            || str_contains($message, 'ssl')
            || str_contains($message, 'ca bundle')
            || str_contains($message, 'unable to get local issuer')
        ) {
            return new ChannelConnectionException(
                'TLS_CERTIFICATE_ERROR',
                'PHP could not verify Telegram HTTPS. Configure a trusted CA bundle for PHP cURL/OpenSSL; do not disable TLS verification.'
            );
        }

        if (
            str_contains($message, 'could not resolve')
            || str_contains($message, 'name resolution')
            || str_contains($message, 'getaddrinfo')
            || str_contains($message, 'dns')
        ) {
            return new ChannelConnectionException(
                'DNS_ERROR',
                'PHP could not resolve api.telegram.org. Check DNS, VPN/proxy and firewall settings.'
            );
        }

        if (
            str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'operation timed out')
        ) {
            return new ChannelConnectionException(
                'CONNECTION_TIMEOUT',
                'The Telegram HTTPS connection timed out. Check firewall, proxy/VPN and outbound port 443 access.'
            );
        }

        if (
            str_contains($message, 'connection refused')
            || str_contains($message, 'failed to connect')
            || str_contains($message, 'could not connect')
        ) {
            return new ChannelConnectionException(
                'CONNECTION_FAILED',
                'PHP could not open an HTTPS connection to api.telegram.org on port 443.'
            );
        }

        return new ChannelConnectionException(
            'NETWORK_ERROR',
            'TradeX could not reach Telegram for connection verification.'
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
