<?php

namespace App\Services\Screener\Transports;

use App\Contracts\Screener\DerivWebSocketTransport;
use App\Exceptions\ScreenerProviderException;
use JsonException;
use Throwable;

class NativeDerivWebSocketTransport implements DerivWebSocketTransport
{
    public function requestMany(array $requests): array
    {
        if ($requests === []) {
            return [];
        }

        $socket = $this->connect();

        try {
            $responses = [];

            foreach ($requests as $request) {
                if (! is_array($request)) {
                    continue;
                }

                try {
                    $payload = json_encode(
                        $request,
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
                    );
                } catch (JsonException) {
                    throw new ScreenerProviderException(
                        'SCREENER_PROVIDER_INVALID_REQUEST',
                        'Deriv market data request could not be encoded.'
                    );
                }

                $this->writeFrame(
                    $socket,
                    $payload,
                    0x1
                );

                $responses[] = $this->readResponse(
                    $socket,
                    isset($request['req_id'])
                        ? (int) $request['req_id']
                        : null
                );
            }

            return $responses;
        } catch (ScreenerProviderException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_UNAVAILABLE',
                'Deriv market data is temporarily unavailable.'
            );
        } finally {
            if (is_resource($socket)) {
                @fclose($socket);
            }
        }
    }

    public function endpointHost(): ?string
    {
        $host = parse_url(
            (string) config(
                'tradex_screener.providers.deriv.websocket_url',
                'wss://api.derivws.com/trading/v1/options/ws/public'
            ),
            PHP_URL_HOST
        );

        return is_string($host) && $host !== ''
            ? strtolower($host)
            : null;
    }

    /**
     * @return resource
     */
    private function connect()
    {
        $url = trim((string) config(
            'tradex_screener.providers.deriv.websocket_url',
            'wss://api.derivws.com/trading/v1/options/ws/public'
        ));

        $parts = parse_url($url);

        if (! is_array($parts)) {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_NOT_CONFIGURED',
                'Deriv market data endpoint is not configured.'
            );
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = (int) ($parts['port'] ?? 443);
        $path = (string) ($parts['path'] ?? '/');
        $query = (string) ($parts['query'] ?? '');

        if ($query !== '') {
            $path .= '?'.$query;
        }

        $allowedHosts = array_values(array_filter(array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            (array) config(
                'tradex_screener.providers.deriv.allowed_hosts',
                ['api.derivws.com']
            )
        )));

        if (
            $scheme !== 'wss'
            || $host === ''
            || $port < 1
            || $port > 65535
            || ! in_array($host, $allowedHosts, true)
        ) {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_NOT_CONFIGURED',
                'Deriv market data endpoint is not allowed.'
            );
        }

        $connectTimeout = max(
            1,
            min(
                15,
                (int) config(
                    'tradex_screener.providers.deriv.connect_timeout_seconds',
                    5
                )
            )
        );

        $readTimeout = max(
            2,
            min(
                30,
                (int) config(
                    'tradex_screener.providers.deriv.timeout_seconds',
                    10
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
                'Deriv market data is temporarily unavailable.'
            );
        }

        stream_set_timeout($socket, $readTimeout);

        try {
            $this->handshake(
                $socket,
                $host,
                $port,
                $path
            );
        } catch (Throwable $exception) {
            @fclose($socket);
            throw $exception;
        }

        return $socket;
    }

    /**
     * @param  resource  $socket
     */
    private function handshake(
        $socket,
        string $host,
        int $port,
        string $path
    ): void {
        $key = base64_encode(random_bytes(16));
        $hostHeader = $port === 443
            ? $host
            : $host.':'.$port;

        $request = "GET {$path} HTTP/1.1\r\n"
            ."Host: {$hostHeader}\r\n"
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
                    'tradex_screener.providers.deriv.max_handshake_bytes',
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
                    'Deriv market data returned an invalid WebSocket handshake.'
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
                'Deriv market data WebSocket connection was rejected.'
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
                'Deriv market data returned an invalid WebSocket handshake.'
            );
        }
    }

    /**
     * @param  resource  $socket
     * @return array<string, mixed>
     */
    private function readResponse(
        $socket,
        ?int $expectedRequestId
    ): array {
        for ($attempt = 0; $attempt < 20; $attempt++) {
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
                    'Deriv market data returned an invalid response.'
                );
            }

            if (! is_array($decoded)) {
                continue;
            }

            if (
                $expectedRequestId === null
                || ! array_key_exists('req_id', $decoded)
                || (int) $decoded['req_id'] === $expectedRequestId
            ) {
                return $decoded;
            }
        }

        throw new ScreenerProviderException(
            'SCREENER_PROVIDER_INVALID_RESPONSE',
            'Deriv market data response did not match the request.'
        );
    }

    /**
     * @param  resource  $socket
     */
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
                $extended = unpack(
                    'nlength',
                    $this->readBytes($socket, 2)
                );
                $length = (int) ($extended['length'] ?? 0);
            } elseif ($length === 127) {
                $extended = unpack(
                    'Nhigh/Nlow',
                    $this->readBytes($socket, 8)
                );

                $high = (int) ($extended['high'] ?? 0);
                $low = (int) ($extended['low'] ?? 0);
                $length = ($high * 4294967296) + $low;
            }

            $maxBytes = max(
                65536,
                min(
                    16777216,
                    (int) config(
                        'tradex_screener.providers.deriv.max_response_bytes',
                        4194304
                    )
                )
            );

            if ($length < 0 || $length > $maxBytes) {
                throw new ScreenerProviderException(
                    'SCREENER_PROVIDER_INVALID_RESPONSE',
                    'Deriv market data response exceeded the allowed size.'
                );
            }

            $mask = $masked
                ? $this->readBytes($socket, 4)
                : null;

            $payload = $length > 0
                ? $this->readBytes($socket, (int) $length)
                : '';

            if (is_string($mask)) {
                $payload = $this->applyMask(
                    $payload,
                    $mask
                );
            }

            if ($opcode === 0x8) {
                throw new ScreenerProviderException(
                    'SCREENER_PROVIDER_UNAVAILABLE',
                    'Deriv market data connection closed unexpectedly.'
                );
            }

            if ($opcode === 0x9) {
                $this->writeFrame(
                    $socket,
                    $payload,
                    0xA
                );
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
                    'Deriv market data returned an unsupported WebSocket frame.'
                );
            }

            if ($finished && $started) {
                return $message;
            }
        }
    }

    /**
     * @param  resource  $socket
     */
    private function writeFrame(
        $socket,
        string $payload,
        int $opcode
    ): void {
        $length = strlen($payload);

        if ($length > 65535) {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_INVALID_REQUEST',
                'Deriv market data request exceeded the allowed size.'
            );
        }

        $header = chr(0x80 | ($opcode & 0x0f));

        if ($length <= 125) {
            $header .= chr(0x80 | $length);
        } else {
            $header .= chr(0x80 | 126).pack('n', $length);
        }

        $mask = random_bytes(4);
        $maskedPayload = $this->applyMask(
            $payload,
            $mask
        );

        $this->writeAll(
            $socket,
            $header.$mask.$maskedPayload
        );
    }

    private function applyMask(
        string $payload,
        string $mask
    ): string {
        $result = '';
        $length = strlen($payload);

        for ($index = 0; $index < $length; $index++) {
            $result .= $payload[$index]
                ^ $mask[$index % 4];
        }

        return $result;
    }

    /**
     * @param  resource  $socket
     */
    private function writeAll(
        $socket,
        string $data
    ): void {
        $offset = 0;
        $length = strlen($data);

        while ($offset < $length) {
            $written = fwrite(
                $socket,
                substr($data, $offset)
            );

            if ($written === false || $written === 0) {
                throw new ScreenerProviderException(
                    'SCREENER_PROVIDER_UNAVAILABLE',
                    'Deriv market data connection could not send the request.'
                );
            }

            $offset += $written;
        }
    }

    /**
     * @param  resource  $socket
     */
    private function readBytes(
        $socket,
        int $length
    ): string {
        $buffer = '';

        while (strlen($buffer) < $length) {
            $chunk = fread(
                $socket,
                $length - strlen($buffer)
            );

            if ($chunk === false || $chunk === '') {
                $this->throwReadFailure($socket);
            }

            $buffer .= $chunk;
        }

        return $buffer;
    }

    /**
     * @param  resource  $socket
     */
    private function throwReadFailure($socket): never
    {
        $meta = stream_get_meta_data($socket);

        if ((bool) ($meta['timed_out'] ?? false)) {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_TIMEOUT',
                'Deriv market data request timed out.'
            );
        }

        throw new ScreenerProviderException(
            'SCREENER_PROVIDER_UNAVAILABLE',
            'Deriv market data connection ended unexpectedly.'
        );
    }
}
