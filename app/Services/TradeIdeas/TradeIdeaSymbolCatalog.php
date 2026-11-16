<?php

namespace App\Services\TradeIdeas;

use App\Models\DailyTradeIdea;
use App\Models\TradeIdea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class TradeIdeaSymbolCatalog
{
    /**
     * @return array<string, array<int, array<string, string>>>
     */
    public function grouped(): array
    {
        $catalog = [
            'FOREX' => [],
            'CRYPTO' => [],
            'INDICES' => [],
            'DERIV' => [],
            'COMMODITY' => [],
            'STOCKS' => [],
        ];

        $this->mergeList($catalog['FOREX'], (array) config('tradex_screener.forex.symbols', []));
        $this->mergeMap($catalog['FOREX'], (array) config('ic_markets_forex.instruments', []));
        // Keep metal and energy CFDs in their own market category.
        $this->mergeMap($catalog['COMMODITY'], (array) config('trading_instruments.markets.commodity.instruments', []));
        $this->mergeMap($catalog['COMMODITY'], (array) config('ic_markets_commodities.instruments', []));

        $this->mergeList($catalog['CRYPTO'], (array) config('tradex_screener.crypto.symbols', []));
        $this->mergeMap($catalog['CRYPTO'], (array) config('ic_markets_crypto.instruments', []));

        $this->mergeList($catalog['DERIV'], (array) config('tradex_screener.deriv.symbols', []));
        $this->mergeMap($catalog['DERIV'], (array) config('trading_instruments.markets.deriv.instruments', []));

        $this->mergeList($catalog['STOCKS'], (array) config('tradex_screener_stocks.symbols', []));
        $this->mergeExistingIdeas($catalog);

        foreach ($catalog as &$items) {
            $items = array_values($items);
            usort(
                $items,
                static fn (array $left, array $right): int => strnatcasecmp(
                    $left['symbol'],
                    $right['symbol']
                )
            );
        }
        unset($items);

        return $catalog;
    }

    /**
     * Keep manually entered and CSE symbols discoverable after an admin has
     * used them, even when a live provider catalog does not cover that market.
     *
     * @param  array<string, array<string, array<string, string>>>  $catalog
     */
    private function mergeExistingIdeas(array &$catalog): void
    {
        foreach ([TradeIdea::class, DailyTradeIdea::class] as $modelClass) {
            /** @var Model $model */
            $model = new $modelClass;

            if (! Schema::hasTable($model->getTable())) {
                continue;
            }

            $modelClass::query()
                ->select(['category', 'symbol', 'instrument_name', 'market_region'])
                ->whereNotNull('symbol')
                ->orderByDesc('id')
                ->limit(500)
                ->get()
                ->each(function (Model $idea) use (&$catalog): void {
                    $category = strtoupper(trim((string) $idea->getAttribute('category')));

                    if (! isset($catalog[$category])) {
                        return;
                    }

                    $symbol = strtoupper(trim((string) $idea->getAttribute('symbol')));
                    if ($symbol === '' || isset($catalog[$category][$symbol])) {
                        return;
                    }

                    $this->add(
                        $catalog[$category],
                        $symbol,
                        [
                            'name' => (string) ($idea->getAttribute('instrument_name') ?: $idea->getAttribute('symbol')),
                            'region' => (string) $idea->getAttribute('market_region'),
                        ]
                    );
                });
        }
    }

    /**
     * @param  array<string, array<string, string>>  $target
     * @param  array<int, mixed>  $items
     */
    private function mergeList(array &$target, array $items): void
    {
        foreach ($items as $item) {
            if (! is_array($item) || ! (bool) ($item['enabled'] ?? true)) {
                continue;
            }

            $this->add($target, (string) ($item['symbol'] ?? ''), $item);
        }
    }

    /**
     * @param  array<string, array<string, string>>  $target
     * @param  array<string, mixed>  $items
     */
    private function mergeMap(array &$target, array $items): void
    {
        foreach ($items as $symbol => $item) {
            if (! is_array($item) || ! (bool) ($item['enabled'] ?? true)) {
                continue;
            }

            $this->add($target, (string) $symbol, $item);
        }
    }

    /**
     * @param  array<string, array<string, string>>  $target
     * @param  array<string, mixed>  $item
     */
    private function add(array &$target, string $symbol, array $item): void
    {
        $symbol = strtoupper(trim($symbol));

        if ($symbol === '') {
            return;
        }

        $target[$symbol] = [
            'symbol' => $symbol,
            'name' => trim((string) ($item['name'] ?? $symbol)),
            'provider_symbol' => trim((string) ($item['provider_symbol'] ?? $symbol)),
            'region' => strtoupper(trim((string) ($item['region'] ?? ''))),
            'exchange' => strtoupper(trim((string) ($item['exchange'] ?? ''))),
        ];
    }
}
