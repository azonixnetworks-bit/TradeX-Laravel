<?php

namespace App\Services\Trading;

use App\Services\Screener\Providers\TwelveDataForexProvider;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ForexUsdConversionService
{
    public function __construct(
        private readonly TwelveDataForexProvider $provider
    ) {
    }

    /** @return array{rate: float, source: string, symbol: ?string} */
    public function resolve(
        string $quoteCurrency,
        mixed $manualRate = null
    ): array {
        $quoteCurrency = strtoupper(trim($quoteCurrency));

        if ($quoteCurrency === 'USD') {
            return [
                'rate' => 1.0,
                'source' => 'USD_QUOTE',
                'symbol' => null,
            ];
        }

        if (is_numeric($manualRate) && (float) $manualRate > 0) {
            return [
                'rate' => (float) $manualRate,
                'source' => 'MANUAL',
                'symbol' => null,
            ];
        }

        [$providerSymbol, $inverse] = $this->conversionSymbol($quoteCurrency);

        if (! $this->provider->configured()) {
            throw new DomainException(
                sprintf(
                    '%s needs a %s→USD conversion rate. Configure the TradeX Twelve Data Forex provider or enter the conversion rate manually.',
                    $quoteCurrency,
                    $quoteCurrency
                )
            );
        }

        $cacheKey = 'tradex:risk-reward:fx-conversion:'.str_replace('/', '-', $providerSymbol);

        try {
            $close = Cache::remember(
                $cacheKey,
                now()->addSeconds(60),
                function () use ($providerSymbol): float {
                    $series = $this->provider->timeSeries(
                        [$providerSymbol],
                        '5min',
                        2
                    );

                    $candles = $series[$providerSymbol] ?? null;

                    if (! is_array($candles) || $candles === []) {
                        $candles = reset($series);
                    }

                    if (! is_array($candles) || $candles === []) {
                        throw new DomainException('Forex conversion market data returned no usable candles.');
                    }

                    $last = end($candles);
                    $close = is_array($last)
                        ? (float) ($last['close'] ?? 0)
                        : 0.0;

                    if ($close <= 0) {
                        throw new DomainException('Forex conversion market data returned an invalid price.');
                    }

                    return $close;
                }
            );
        } catch (DomainException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw new DomainException(
                sprintf(
                    'Live %s→USD conversion is unavailable. Enter the current conversion rate manually and calculate again.',
                    $quoteCurrency
                )
            );
        }

        $rate = $inverse
            ? 1.0 / (float) $close
            : (float) $close;

        if (! is_finite($rate) || $rate <= 0) {
            throw new DomainException('Unable to resolve a valid quote-currency to USD conversion rate.');
        }

        return [
            'rate' => $rate,
            'source' => 'TWELVE_DATA',
            'symbol' => $providerSymbol,
        ];
    }

    /** @return array{0: string, 1: bool} */
    private function conversionSymbol(string $currency): array
    {
        $direct = [
            'AUD' => 'AUD/USD',
            'EUR' => 'EUR/USD',
            'GBP' => 'GBP/USD',
            'NZD' => 'NZD/USD',
        ];

        if (isset($direct[$currency])) {
            return [$direct[$currency], false];
        }

        $inverseCurrencies = [
            'CAD',
            'CHF',
            'CNH',
            'CZK',
            'DKK',
            'HKD',
            'HUF',
            'JPY',
            'MXN',
            'NOK',
            'PLN',
            'RUB',
            'SEK',
            'SGD',
            'THB',
            'TRY',
            'ZAR',
        ];

        if (in_array($currency, $inverseCurrencies, true)) {
            return ['USD/'.$currency, true];
        }

        throw new DomainException(
            sprintf(
                'Automatic USD conversion is not configured for quote currency %s. Enter a manual quote-to-USD conversion rate.',
                $currency
            )
        );
    }
}
