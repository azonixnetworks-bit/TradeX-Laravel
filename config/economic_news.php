<?php

$countryMap = [
    'united states' => ['code' => 'US', 'currency' => 'USD'],
    'euro area' => ['code' => 'EU', 'currency' => 'EUR'],
    'united kingdom' => ['code' => 'GB', 'currency' => 'GBP'],
    'japan' => ['code' => 'JP', 'currency' => 'JPY'],
    'australia' => ['code' => 'AU', 'currency' => 'AUD'],
    'new zealand' => ['code' => 'NZ', 'currency' => 'NZD'],
    'canada' => ['code' => 'CA', 'currency' => 'CAD'],
    'switzerland' => ['code' => 'CH', 'currency' => 'CHF'],
    'china' => ['code' => 'CN', 'currency' => 'CNY'],
];

$currencyCountryMap = [
    'USD' => 'US',
    'EUR' => 'EU',
    'GBP' => 'GB',
    'JPY' => 'JP',
    'AUD' => 'AU',
    'NZD' => 'NZ',
    'CAD' => 'CA',
    'CHF' => 'CH',
    'CNY' => 'CN',
];

$countries = array_values(array_filter(array_map(
    static fn (string $country): string => trim($country),
    explode(',', (string) env(
        'TRADEX_ECONOMIC_NEWS_COUNTRIES',
        'United States,Euro Area,United Kingdom,Japan,Australia,New Zealand,Canada,Switzerland'
    ))
)));

return [
    'sync' => [
        'enabled' => env('TRADEX_ECONOMIC_NEWS_SYNC_ENABLED', true),
        'provider' => env('TRADEX_ECONOMIC_NEWS_PROVIDER', 'forex_factory_json'),
        'frequency_minutes' => max(1, min(30, (int) env('TRADEX_ECONOMIC_NEWS_SYNC_FREQUENCY_MINUTES', 5))),
        'lookback_days' => max(0, min(14, (int) env('TRADEX_ECONOMIC_NEWS_LOOKBACK_DAYS', 2))),
        'lookahead_days' => max(1, min(90, (int) env('TRADEX_ECONOMIC_NEWS_LOOKAHEAD_DAYS', 14))),
        'minimum_importance' => max(1, min(3, (int) env('TRADEX_ECONOMIC_NEWS_MIN_IMPORTANCE', 2))),
        'auto_publish' => env('TRADEX_ECONOMIC_NEWS_AUTO_PUBLISH', true),
        'countries' => $countries,
    ],

    'providers' => [
        'forex_factory_json' => [
            'feed_url' => env(
                'TRADEX_FOREX_FACTORY_JSON_URL',
                'https://nfs.faireconomy.media/ff_calendar_thisweek.json'
            ),
            'timeout_seconds' => max(3, (int) env('TRADEX_FOREX_FACTORY_TIMEOUT_SECONDS', 15)),
            'connect_timeout_seconds' => max(2, (int) env('TRADEX_FOREX_FACTORY_CONNECT_TIMEOUT_SECONDS', 5)),
            'user_agent' => env('TRADEX_FOREX_FACTORY_USER_AGENT', 'TradeX Economic News/1.0'),
        ],

        'trading_economics' => [
            'base_url' => env('TRADEX_TRADING_ECONOMICS_BASE_URL', 'https://api.tradingeconomics.com'),
            'api_key' => env('TRADEX_TRADING_ECONOMICS_API_KEY'),
            'timeout_seconds' => max(3, (int) env('TRADEX_TRADING_ECONOMICS_TIMEOUT_SECONDS', 15)),
            'connect_timeout_seconds' => max(2, (int) env('TRADEX_TRADING_ECONOMICS_CONNECT_TIMEOUT_SECONDS', 5)),
        ],
    ],

    'country_map' => $countryMap,
    'currency_country_map' => $currencyCountryMap,
];
