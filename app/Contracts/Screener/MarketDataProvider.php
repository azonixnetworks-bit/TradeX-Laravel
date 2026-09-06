<?php

namespace App\Contracts\Screener;

interface MarketDataProvider
{
    public function name(): string;

    public function market(): string;

    public function configured(): bool;

    /**
     * @param  array<int, string>  $symbols
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function timeSeries(
        array $symbols,
        string $interval,
        int $outputSize
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function health(): array;
}
