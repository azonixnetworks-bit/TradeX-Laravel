<?php

namespace App\Services\Screener\Transports;

use App\Contracts\Screener\CTraderJsonTransport;
use App\Exceptions\ScreenerProviderException;
use JsonException;
use Throwable;

class NativeCTraderJsonWebSocketTransport implements CTraderJsonTransport
{
    public function exchange(array $steps): array
    {
        if ($steps === []) {
            return [];
        }

        $socket = $this->connect();

        try {
            $responses = [];

            foreach ($steps as $step) {
                if (! is_array($step)) {
                    continue;
                }

                $request = $step['request'] ?? null;
                $expectedPayloadType = (int) (
                    $step['expect_payload_type'] ?? 0
                );
                $delayMs = max(
                    0,
                    min(5000, (int) ($step['delay_ms'] ?? 0))
                );

                if (! is_array($request) || $expectedPayloadType < 1) {
                    throw new ScreenerProviderException(
                        'SCREENER_PROVIDER_INVALID_REQUEST',
                        'cTrader market data request is invalid.'
                    );
                }

                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }

                try {
                    $payload = json_encode(
                        $request,
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
                    );
                } catch (JsonException) {
                    throw new ScreenerProviderException(
                        'SCREENER_PROVIDER_INVALID_REQUEST',
                        'cTrader market data request could not be encoded.'
                    );
                }

                $this->writeFrame($socket, $payload, 0x1);

                $responses[] = $this->readResponse(
                    $socket,
                    (string) ($request['clientMsgId'] ?? ''),
                    $expectedPayloadType
                );
            }

            return $responses;
        } catch (ScreenerProviderException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_UNAVAILABLE',
                'cTrader market data is temporarily unavailable.'
            );
        } finally {
            if (is_resource($socket)) {
                @fclose($socket);
            }
        }
    }

    public function endpointHost(): ?string
    {
        $parts = parse_url($this->endpointUrl());
        $host = is_array($parts)
            ? ($parts['host'] ?? null)
            : null;

        return is_string($host) && $host !== ''
            ? strtolower($host)
            : null;
    }

    private function endpointUrl(): string
    {
        $environment = strtoupper(trim((string) config(
            'tradex_screener_ctrader.environment',
            'DEMO'
        )));

        return $environment === 'LIVE'
            ? trim((string) config(
                'tradex_screener_ctrader.live_websocket_url',
                'wss://live.ctraderapi.com:5036/'
            ))
            : trim((string) config(
                'tradex_screener_ctrader.demo_websocket_url',
                'wss://demo.ctraderapi.com:5036/'
            ));
    }

    /** @return resource */
    private function connect()
    {
        $url = $this->endpointUrl();
        $parts = parse_url($url);

        if (! is_array($parts)) {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_NOT_CONFIGURED',
                'cTrader market data endpoint is not configured.'
            );
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = (int) ($parts['port'] ?? 5036);
        $path = (string) ($parts['path'] ?? '/');

        if ($path === '') {
            $path = '/';
        }

        $allowedHosts = array_values(array_filter(array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            (array) config(
                'tradex_screener_ctrader.allowed_hosts',
                ['demo.ctraderapi.com', 'live.ctraderapi.com']
            )
        )));

        if (
            $scheme !== 'wss'
            || $host === ''
            || $port !== 5036
            || ! in_array($host, $allowedHosts, true)
        ) {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_NOT_CONFIGURED',
                'cTrader market data endpoint is not allowed.'
            );
        }

        $connectTimeout = max(
            1,
            min(
                15,
                (int) config(
                    'tradex_screener_ctrader.connect_timeout_seconds',
                    6
                )
            )
        );
        $readTimeout = max(
            2,
            min(
                30,
                (int) config(
                    'tradex_screener_ctrader.timeout_seconds',
                    12
                )
            )
        );

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $host,
                'SNI_enabled' => true,
                'disable_compression' => true,
            ],
        ]);

        $errorNumber = 0;
        $errorMessage = '';
        $socket = @stream_socket_client(
            'tls://'.$host.':'.$port,
            $errorNumber,
            $errorMessage,
            $connectTimeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (! is_resource($socket)) {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_UNAVAILABLE',
                'cTrader market data is temporarily unavailable.'
            );
        }

        stream_set_timeout($socket, $readTimeout);

        try {
            $this->handshake($socket, $host, $port, $path);
        } catch (Throwable $exception) {
            @fclose($socket);
            throw $exception;
        }

        return $socket;
    }

    /** @param resource $socket */
    private function handshake(
        $socket,
        string $host,
        int $port,
        string $path
    ): void {
        $key = base64_encode(random_bytes(16));
        $request = "GET {$path} HTTP/1.1\r\n"
            ."Host: {$host}:{$port}\r\n"
            ."Upgrade: websocket\r\n"
            ."Connection: Upgrade\r\n"
            ."Sec-WebSocket-Key: {$key}\r\n"
            ."Sec-WebSocket-Version: 13\r\n"
            ."User-Agent: TradeX-Screener/1.0\r\n"
            ."\r\n";

        $this->writeAll($socket, $request);

        $headers = '';
        $limit = max(
            4096,
            min(
                65536,
                (int) config(
                    'tradex_screener_ctrader.max_handshake_bytes',
                    16384
                )
            )
        );

        while (! str_contains($headers, "\r\n\r\n")) {
            $line = fgets($socket, 4096);

            if ($line === false) {
                $this->throwReadFailure($socket);
            }

            $headers .= $line;

            if (strlen($headers) > $limit) {
                throw new ScreenerProviderException(
                    'SCREENER_PROVIDER_INVALID_RESPONSE',
                    'cTrader returned an invalid WebSocket handshake.'
                );
            }
        }

        $lines = preg_split('/\r\n/', trim($headers));
        $statusLine = is_array($lines) && isset($lines[0])
            ? (string) $lines[0]
            : '';

        if (! preg_match('/^HTTP\/1\.[01]\s+101\b/i', $statusLine)) {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_HTTP_ERROR',
                'cTrader market data WebSocket connection was rejected.'
            );
        }

        $accept = null;

        foreach ((array) $lines as $line) {
            if (! str_contains((string) $line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', (string) $line, 2);

            if (strtolower(trim($name)) === 'sec-websocket-accept') {
                $accept = trim($value);
                break;
            }
        }

        $expected = base64_encode(
            sha1(
                $key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11',
                true
            )
        );

        if (! is_string($accept) || ! hash_equals($expected, $accept)) {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_INVALID_RESPONSE',
                'cTrader returned an invalid WebSocket handshake.'
            );
        }
    }

    /** @param resource $socket @return array<string, mixed> */
    private function readResponse(
        $socket,
        string $expectedClientMsgId,
        int $expectedPayloadType
    ): array {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $message = $this->readTextMessage($socket);

            try {
                $decoded = json_decode(
                    $message,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            } catch (JsonException) {
                throw new ScreenerProviderException(
                    'SCREENER_PROVIDER_INVALID_RESPONSE',
                    'cTrader market data returned an invalid JSON response.'
                );
            }

            if (! is_array($decoded)) {
                continue;
            }

            $payload = is_array($decoded['payload'] ?? null)
                ? $decoded['payload']
                : [];
            $errorCode = trim((string) (
                $payload['errorCode']
                ?? $decoded['errorCode']
                ?? ''
            ));

            if ($errorCode !== '') {
                $safeCode = preg_replace(
                    '/[^A-Z0-9_\-]/i',
                    '',
                    $errorCode
                );

                throw new ScreenerProviderException(
                    'SCREENER_CTRADER_'.strtoupper((string) $safeCode),
                    'cTrader rejected the market data request.'
                );
            }

            $payloadType = (int) ($decoded['payloadType'] ?? 0);
            $clientMsgId = (string) ($decoded['clientMsgId'] ?? '');

            if (
                $payloadType === $expectedPayloadType
                && (
                    $expectedClientMsgId === ''
                    || $clientMsgId === ''
                    || hash_equals($expectedClientMsgId, $clientMsgId)
                )
            ) {
                return $decoded;
            }
        }

        throw new ScreenerProviderException(
            'SCREENER_PROVIDER_INVALID_RESPONSE',
            'cTrader market data response did not match the request.'
        );
    }

    /** @param resource $socket */
    private function readTextMessage($socket): string
    {
        $message = '';
        $started = false;

        while (true) {
            $header = $this->readBytes($socket, 2);
            $first = ord($header[0]);
            $second = ord($header[1]);
            $finished = ($first & 0x80) !== 0;
            $opcode = $first & 0x0f;
            $masked = ($second & 0x80) !== 0;
            $length = $second & 0x7f;

            if ($length === 126) {
                $extended = unpack('nlength', $this->readBytes($socket, 2));
                $length = (int) ($extended['length'] ?? 0);
            } elseif ($length === 127) {
                $extended = unpack('Nhigh/Nlow', $this->readBytes($socket, 8));
                $high = (int) ($extended['high'] ?? 0);
                $low = (int) ($extended['low'] ?? 0);
                $length = ($high * 4294967296) + $low;
            }

            $maxBytes = max(
                65536,
                min(
                    16777216,
                    (int) config(
                        'tradex_screener_ctrader.max_response_bytes',
                        4194304
                    )
                )
            );

            if ($length < 0 || $length > $maxBytes) {
                throw new ScreenerProviderException(
                    'SCREENER_PROVIDER_INVALID_RESPONSE',
                    'cTrader market data response exceeded the allowed size.'
                );
            }

            $mask = $masked
                ? $this->readBytes($socket, 4)
                : null;
            $payload = $length > 0
                ? $this->readBytes($socket, (int) $length)
                : '';

            if (is_string($mask)) {
                $payload = $this->applyMask($payload, $mask);
            }

            if ($opcode === 0x8) {
                throw new ScreenerProviderException(
                    'SCREENER_PROVIDER_UNAVAILABLE',
                    'cTrader market data connection closed unexpectedly.'
                );
            }

            if ($opcode === 0x9) {
                $this->writeFrame($socket, $payload, 0xA);
                continue;
            }

            if ($opcode === 0xA) {
                continue;
            }

            if ($opcode === 0x1) {
                $started = true;
                $message = $payload;
            } elseif ($opcode === 0x0 && $started) {
                $message .= $payload;
            } else {
                throw new ScreenerProviderException(
                    'SCREENER_PROVIDER_INVALID_RESPONSE',
                    'cTrader returned an unsupported WebSocket frame.'
                );
            }

            if ($finished && $started) {
                return $message;
            }
        }
    }

    /** @param resource $socket */
    private function writeFrame(
        $socket,
        string $payload,
        int $opcode
    ): void {
        $length = strlen($payload);

        if ($length > 65535) {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_INVALID_REQUEST',
                'cTrader market data request exceeded the allowed size.'
            );
        }

        $header = chr(0x80 | ($opcode & 0x0f));

        if ($length <= 125) {
            $header .= chr(0x80 | $length);
        } else {
            $header .= chr(0x80 | 126).pack('n', $length);
        }

        $mask = random_bytes(4);
        $this->writeAll(
            $socket,
            $header.$mask.$this->applyMask($payload, $mask)
        );
    }

    private function applyMask(string $payload, string $mask): string
    {
        $result = '';
        $length = strlen($payload);

        for ($index = 0; $index < $length; $index++) {
            $result .= $payload[$index] ^ $mask[$index % 4];
        }

        return $result;
    }

    /** @param resource $socket */
    private function writeAll($socket, string $data): void
    {
        $offset = 0;
        $length = strlen($data);

        while ($offset < $length) {
            $written = fwrite($socket, substr($data, $offset));

            if ($written === false || $written === 0) {
                throw new ScreenerProviderException(
                    'SCREENER_PROVIDER_UNAVAILABLE',
                    'cTrader market data connection could not send the request.'
                );
            }

            $offset += $written;
        }
    }

    /** @param resource $socket */
    private function readBytes($socket, int $length): string
    {
        $buffer = '';

        while (strlen($buffer) < $length) {
            $chunk = fread($socket, $length - strlen($buffer));

            if ($chunk === false || $chunk === '') {
                $this->throwReadFailure($socket);
            }

            $buffer .= $chunk;
        }

        return $buffer;
    }

    /** @param resource $socket */
    private function throwReadFailure($socket): never
    {
        $meta = stream_get_meta_data($socket);

        if ((bool) ($meta['timed_out'] ?? false)) {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_TIMEOUT',
                'cTrader market data request timed out.'
            );
        }

        throw new ScreenerProviderException(
            'SCREENER_PROVIDER_UNAVAILABLE',
            'cTrader market data connection ended unexpectedly.'
        );
    }
}
