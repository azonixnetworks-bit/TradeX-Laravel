<?php

namespace App\Contracts\Screener;

interface CTraderJsonTransport
{
    /**
     * Execute a sequence of cTrader JSON messages on one authenticated
     * WebSocket connection.
     *
     * Each item must contain:
     * - request: cTrader JSON envelope
     * - expect_payload_type: expected response payload type
     * - delay_ms: optional delay before send (used for historical rate limits)
     *
     * @param  array<int, array<string, mixed>>  $steps
     * @return array<int, array<string, mixed>>
     */
    public function exchange(array $steps): array;

    public function endpointHost(): ?string;
}
