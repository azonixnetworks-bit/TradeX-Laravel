<?php

namespace App\Services\Screener;

use InvalidArgumentException;

class StocksScreenerEngine extends ScreenerEngine
{
    private ?string $activeStockTimeframe = null;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function markets(): array
    {
        $markets = parent::markets();

        $markets['STOCKS'] = [
            'label' => 'Stocks',
            'enabled' => (bool) config(
                'tradex_screener_stocks.enabled',
                true
            ),
            'status' => 'ACTIVE',
        ];

        return $markets;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function stockRegions(): array
    {
        return (array) config(
            'tradex_screener_stocks.regions',
            []
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function symbols(string $market): array
    {
        $market = strtoupper(trim($market));

        if ($market !== 'STOCKS') {
            return parent::symbols($market);
        }

        return array_values(array_filter(
            (array) config(
                'tradex_screener_stocks.symbols',
                []
            ),
            function (mixed $symbol): bool {
                if (
                    ! is_array($symbol)
                    || ! (bool) ($symbol['enabled'] ?? false)
                ) {
                    return false;
                }

                if ($this->activeStockTimeframe === null) {
                    return true;
                }

                $supported = array_map(
                    'strtoupper',
                    (array) ($symbol['supported_timeframes'] ?? [])
                );

                return $supported === []
                    || in_array(
                        $this->activeStockTimeframe,
                        $supported,
                        true
                    );
            }
        ));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function screen(array $filters): array
    {
        $market = strtoupper(trim((string) ($filters['market'] ?? 'FOREX')));

        if ($market !== 'STOCKS') {
            return parent::screen($filters);
        }

        $region = strtoupper(trim((string) ($filters['region'] ?? '')));
        $exchange = strtoupper(trim((string) ($filters['exchange'] ?? '')));
        $timeframe = strtoupper(trim((string) ($filters['timeframe'] ?? 'H1')));

        if ($region !== '') {
            $regionConfig = $this->stockRegions()[$region] ?? null;

            if (! is_array($regionConfig)) {
                throw new InvalidArgumentException(
                    'Unsupported stocks region.'
                );
            }

            if (! (bool) ($regionConfig['enabled'] ?? false)) {
                throw new InvalidArgumentException(
                    'This stocks region is waiting for an approved market-data provider.'
                );
            }

            $supported = array_map(
                'strtoupper',
                (array) ($regionConfig['supported_timeframes'] ?? [])
            );

            if ($supported !== [] && ! in_array($timeframe, $supported, true)) {
                throw new InvalidArgumentException(
                    'This stocks region does not support the selected timeframe.'
                );
            }
        }

        $this->activeStockTimeframe = $timeframe;

        try {
            $data = parent::screen($filters);
        } finally {
            $this->activeStockTimeframe = null;
        }

        $registry = [];

        foreach ($this->symbols('STOCKS') as $symbol) {
            $key = strtoupper((string) ($symbol['symbol'] ?? ''));

            if ($key !== '') {
                $registry[$key] = $symbol;
            }
        }

        $rows = [];

        foreach ((array) ($data['results'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $symbolConfig = $registry[
                strtoupper((string) ($row['symbol'] ?? ''))
            ] ?? [];

            $rowRegion = strtoupper((string) ($symbolConfig['region'] ?? ''));
            $rowExchange = strtoupper((string) ($symbolConfig['exchange'] ?? ''));

            if ($region !== '' && $rowRegion !== $region) {
                continue;
            }

            if ($exchange !== '' && $rowExchange !== $exchange) {
                continue;
            }

            $rows[] = [
                ...$row,
                'region' => $rowRegion,
                'exchange' => $rowExchange,
                'mic_code' => (string) ($symbolConfig['mic_code'] ?? ''),
                'currency' => (string) ($symbolConfig['currency'] ?? ''),
                'data_mode' => (string) ($symbolConfig['data_mode'] ?? ''),
            ];
        }

        $data['count'] = count($rows);
        $data['results'] = array_values($rows);

        return $data;
    }
}
