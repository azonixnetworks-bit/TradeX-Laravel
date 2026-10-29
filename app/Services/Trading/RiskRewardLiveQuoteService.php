<?php

namespace App\Services\Trading;

use App\Contracts\Screener\DerivWebSocketTransport;
use App\Exceptions\ScreenerProviderException;
use App\Services\Screener\Providers\BinanceCryptoProvider;
use App\Services\Screener\Providers\BinanceFuturesCryptoProvider;
use App\Services\Screener\Providers\DerivMarketDataProvider;
use App\Services\Screener\Providers\TwelveDataForexProvider;
use DomainException;
use Illuminate\Support\Facades\Cache;

class RiskRewardLiveQuoteService
{
    public function __construct(
        private readonly TradingInstrumentCatalog $catalog,
        private readonly TwelveDataForexProvider $forexProvider,
        private readonly DerivMarketDataProvider $derivProvider,
        private readonly BinanceCryptoProvider $cryptoProvider,
        private readonly BinanceFuturesCryptoProvider $futuresProvider,
        private readonly DerivWebSocketTransport $derivTransport
    ) {}

    /** @return array<string, mixed> */
    public function quote(string $market, string $symbol, string $side = 'BUY'): array
    {
        $market = strtolower(trim($market));
        $symbol = strtoupper(trim($symbol));
        $side = strtoupper(trim($side));

        if (! in_array($market, ['forex', 'deriv', 'crypto', 'binance_futures'], true)) {
            throw new DomainException('Live entry prices are unavailable for this calculator market.');
        }

        $marketConfig = (array) ($this->catalog->markets()[$market] ?? []);
        $instruments = (array) ($marketConfig['instruments'] ?? []);
        $instrument = $instruments[$symbol] ?? null;

        if (! is_array($instrument)) {
            throw new DomainException('Select a supported symbol from the suggestions.');
        }

        $providerSymbol = trim((string) ($instrument['provider_symbol'] ?? ''));
        if ($providerSymbol === '') {
            $providerSymbol = $this->providerSymbol($market, $symbol) ?? '';
            if ($providerSymbol === '' && $market === 'deriv') {
                $providerSymbol = trim((string) ($instrument['name'] ?? ''));
            }
            if ($providerSymbol === '' && $market === 'crypto') {
                $providerSymbol = $this->cryptoProviderSymbol($symbol);
            }
            $providerSymbol = $providerSymbol !== '' ? $providerSymbol : $symbol;
        }

        $cacheKey = 'tradex:risk-reward:quote:'.sha1($market.'|'.$providerSymbol.'|'.$side);

        try {
            /** @var array<string, mixed> $quote */
            $quote = Cache::remember($cacheKey, now()->addSeconds(12), function () use (
                $market,
                $symbol,
                $providerSymbol,
                $instrument,
                $side
            ): array {
                if ($market === 'deriv') {
                    $usesCTrader = $this->derivProvider->configured();
                    $live = $usesCTrader
                        ? $this->derivProvider->livePrice($providerSymbol, $side)
                        : $this->publicDerivTick($symbol);
                    $price = (float) $live['price'];
                    $precision = (int) $live['digits'];

                    return [
                        'market' => $market,
                        'symbol' => $symbol,
                        'price' => $price,
                        'display_price' => number_format($price, $precision, '.', ''),
                        'precision' => $precision,
                        'observed_at_utc' => $live['observed_at_utc'] ?? now()->utc()->toIso8601String(),
                        'provider' => $usesCTrader
                            ? $this->derivProvider->name()
                            : 'DERIV_PUBLIC',
                    ];
                }

                if ($market === 'forex' && ! $this->forexProvider->configured()) {
                    $live = $this->publicDerivTick(
                        'frx'.str_replace('/', '', $providerSymbol)
                    );
                    $price = (float) $live['price'];
                    $precision = $this->precision(
                        (float) ($instrument['tick_size'] ?? 0.00001)
                    );

                    return [
                        'market' => $market,
                        'symbol' => $symbol,
                        'price' => $price,
                        'display_price' => number_format($price, $precision, '.', ''),
                        'precision' => $precision,
                        'observed_at_utc' => $live['observed_at_utc'],
                        'provider' => 'DERIV_PUBLIC_FOREX',
                    ];
                }

                $provider = match ($market) {
                    'forex' => $this->forexProvider,
                    'binance_futures' => $this->futuresProvider,
                    default => $this->cryptoProvider,
                };

                $interval = $market === 'forex' ? '5min' : '1m';
                $series = $provider->timeSeries([$providerSymbol], $interval, 2);
                $candles = $series[$providerSymbol] ?? reset($series);

                if (! is_array($candles) || $candles === []) {
                    throw new DomainException('Live price is temporarily unavailable for this symbol.');
                }

                $last = end($candles);
                $price = is_array($last) ? (float) ($last['close'] ?? 0) : 0.0;
                if (! is_finite($price) || $price <= 0) {
                    throw new DomainException('Live price is temporarily unavailable for this symbol.');
                }

                $precision = $this->precision((float) ($instrument['tick_size'] ?? 0.00001));

                return [
                    'market' => $market,
                    'symbol' => $symbol,
                    'price' => $price,
                    'display_price' => number_format($price, $precision, '.', ''),
                    'precision' => $precision,
                    'observed_at_utc' => is_array($last)
                        ? ($last['datetime_utc'] ?? now()->utc()->toIso8601String())
                        : now()->utc()->toIso8601String(),
                    'provider' => $provider->name(),
                ];
            });

            return $quote;
        } catch (ScreenerProviderException $exception) {
            throw new DomainException($exception->getMessage());
        }
    }

    private function providerSymbol(string $market, string $symbol): ?string
    {
        $items = (array) config('tradex_screener.'.$market.'.symbols', []);

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (strtoupper((string) ($item['symbol'] ?? '')) === $symbol) {
                $providerSymbol = trim((string) ($item['provider_symbol'] ?? ''));

                return $providerSymbol !== '' ? $providerSymbol : null;
            }
        }

        return null;
    }

    private function precision(float $tickSize): int
    {
        if ($tickSize <= 0) {
            return 5;
        }

        $text = rtrim(rtrim(number_format($tickSize, 8, '.', ''), '0'), '.');
        $point = strpos($text, '.');

        return $point === false ? 0 : min(8, strlen($text) - $point - 1);
    }

    private function cryptoProviderSymbol(string $symbol): string
    {
        $asset = str_ends_with($symbol, 'USD') ? substr($symbol, 0, -3) : $symbol;
        $aliases = [
            'LNK' => 'LINK',
            'DOG' => 'DOGE',
            'AVX' => 'AVAX',
            'MTC' => 'MATIC',
        ];

        return ($aliases[$asset] ?? $asset).'USDT';
    }

    /** @return array{price: float, digits: int, observed_at_utc: string} */
    private function publicDerivTick(string $symbol): array
    {
        $responses = $this->derivTransport->requestMany([[
            'ticks' => trim($symbol),
            'subscribe' => 1,
            'req_id' => 1,
        ]]);

        $payload = is_array($responses[0] ?? null) ? $responses[0] : [];
        $tick = is_array($payload['tick'] ?? null) ? $payload['tick'] : [];
        $price = (float) ($tick['quote'] ?? 0);

        if (! is_finite($price) || $price <= 0) {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_NO_DATA',
                'Live market price is temporarily unavailable for this symbol.'
            );
        }

        $epoch = (int) ($tick['epoch'] ?? 0);

        return [
            'price' => $price,
            'digits' => max(0, min(8, (int) ($tick['pip_size'] ?? 3))),
            'observed_at_utc' => $epoch > 0
                ? gmdate('c', $epoch)
                : now()->utc()->toIso8601String(),
        ];
    }
}
