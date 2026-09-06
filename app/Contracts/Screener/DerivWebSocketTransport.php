<?php

namespace App\Contracts\Screener;

interface DerivWebSocketTransport
{
    /**
     * Execute one or more sequential public Deriv WebSocket requests.
     *
     * @param  array<int, array<string, mixed>>  $requests
     * @return array<int, array<string, mixed>>
     */
    public function requestMany(array $requests): array;

    public function endpointHost(): ?string;
}
