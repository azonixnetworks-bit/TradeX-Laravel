<?php

return [
    /*
    |--------------------------------------------------------------------------
    | TradeX Multi-Screener
    |--------------------------------------------------------------------------
    |
    | SCREENER-01 enables the provider-independent core and Forex adapter.
    | Crypto, Deriv and Stocks are intentionally advertised as COMING_SOON
    | until their dedicated adapters are implemented and tested.
    |
    | Provider timestamps are requested in UTC. User interfaces may localise
    | UTC timestamps through the existing IANA-timezone architecture.
    |
    */

    'enabled' => env(
        'TRADEX_SCREENER_ENABLED',
        true
    ),

    'markets' => [
        'FOREX' => [
            'label' => 'Forex',
            'enabled' => true,
            'status' => 'ACTIVE',
        ],
        'CRYPTO' => [
            'label' => 'Crypto',
            'enabled' => false,
            'status' => 'COMING_SOON',
        ],
        'DERIV' => [
            'label' => 'Deriv',
            'enabled' => false,
            'status' => 'COMING_SOON',
        ],
        'STOCKS' => [
            'label' => 'Stocks',
            'enabled' => false,
            'status' => 'COMING_SOON',
        ],
    ],

    'timeframes' => [
        'M5' => [
            'label' => '5 Minutes',
            'provider_interval' => '5min',
        ],
        'M15' => [
            'label' => '15 Minutes',
            'provider_interval' => '15min',
        ],
        'H1' => [
            'label' => '1 Hour',
            'provider_interval' => '1h',
        ],
        'H4' => [
            'label' => '4 Hours',
            'provider_interval' => '4h',
        ],
        'D1' => [
            'label' => '1 Day',
            'provider_interval' => '1day',
        ],
    ],

    'forex' => [
        'symbols' => [
            [
                'symbol' => 'EURUSD',
                'provider_symbol' => 'EUR/USD',
                'name' => 'Euro / US Dollar',
                'base_currency' => 'EUR',
                'quote_currency' => 'USD',
                'enabled' => true,
            ],
            [
                'symbol' => 'GBPUSD',
                'provider_symbol' => 'GBP/USD',
                'name' => 'British Pound / US Dollar',
                'base_currency' => 'GBP',
                'quote_currency' => 'USD',
                'enabled' => true,
            ],
            [
                'symbol' => 'USDJPY',
                'provider_symbol' => 'USD/JPY',
                'name' => 'US Dollar / Japanese Yen',
                'base_currency' => 'USD',
                'quote_currency' => 'JPY',
                'enabled' => true,
            ],
            [
                'symbol' => 'USDCHF',
                'provider_symbol' => 'USD/CHF',
                'name' => 'US Dollar / Swiss Franc',
                'base_currency' => 'USD',
                'quote_currency' => 'CHF',
                'enabled' => true,
            ],
            [
                'symbol' => 'AUDUSD',
                'provider_symbol' => 'AUD/USD',
                'name' => 'Australian Dollar / US Dollar',
                'base_currency' => 'AUD',
                'quote_currency' => 'USD',
                'enabled' => true,
            ],
            [
                'symbol' => 'NZDUSD',
                'provider_symbol' => 'NZD/USD',
                'name' => 'New Zealand Dollar / US Dollar',
                'base_currency' => 'NZD',
                'quote_currency' => 'USD',
                'enabled' => true,
            ],
            [
                'symbol' => 'USDCAD',
                'provider_symbol' => 'USD/CAD',
                'name' => 'US Dollar / Canadian Dollar',
                'base_currency' => 'USD',
                'quote_currency' => 'CAD',
                'enabled' => true,
            ],
            [
                'symbol' => 'EURGBP',
                'provider_symbol' => 'EUR/GBP',
                'name' => 'Euro / British Pound',
                'base_currency' => 'EUR',
                'quote_currency' => 'GBP',
                'enabled' => true,
            ],
            [
                'symbol' => 'EURJPY',
                'provider_symbol' => 'EUR/JPY',
                'name' => 'Euro / Japanese Yen',
                'base_currency' => 'EUR',
                'quote_currency' => 'JPY',
                'enabled' => true,
            ],
            [
                'symbol' => 'GBPJPY',
                'provider_symbol' => 'GBP/JPY',
                'name' => 'British Pound / Japanese Yen',
                'base_currency' => 'GBP',
                'quote_currency' => 'JPY',
                'enabled' => true,
            ],
        ],
    ],

    'providers' => [
        'twelve_data' => [
            'enabled' => env(
                'TRADEX_TWELVE_DATA_ENABLED',
                true
            ),
            'base_url' => env(
                'TRADEX_TWELVE_DATA_BASE_URL',
                'https://api.twelvedata.com'
            ),
            'api_key' => env(
                'TRADEX_TWELVE_DATA_API_KEY',
                ''
            ),
            'timeout_seconds' => env(
                'TRADEX_TWELVE_DATA_TIMEOUT_SECONDS',
                10
            ),
            'connect_timeout_seconds' => env(
                'TRADEX_TWELVE_DATA_CONNECT_TIMEOUT_SECONDS',
                5
            ),
            'batch_size' => env(
                'TRADEX_TWELVE_DATA_BATCH_SIZE',
                10
            ),
            'output_size' => env(
                'TRADEX_TWELVE_DATA_OUTPUT_SIZE',
                260
            ),
        ],
    ],

    'cache' => [
        'fresh_seconds' => env(
            'TRADEX_SCREENER_CACHE_SECONDS',
            60
        ),
        'stale_seconds' => env(
            'TRADEX_SCREENER_STALE_SECONDS',
            900
        ),
    ],

    'indicators' => [
        'volatility' => [
            'low_max_percent' => 0.35,
            'medium_max_percent' => 0.80,
        ],
    ],

    'rate_limits' => [
        'read_per_minute' => env(
            'TRADEX_SCREENER_READ_PER_MINUTE',
            60
        ),
    ],
];
