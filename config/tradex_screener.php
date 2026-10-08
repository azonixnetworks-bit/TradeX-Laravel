<?php

return [
    /*
    |--------------------------------------------------------------------------
    | TradeX Multi-Screener
    |--------------------------------------------------------------------------
    |
    | SCREENER-01 introduced the provider-independent core and Forex adapter.
    | SCREENER-02 activated Crypto through Binance public Spot market data.
    | SCREENER-03 activates Deriv synthetic-index market data through Deriv's
    | public WebSocket API. Stocks remain COMING_SOON until their dedicated
    | regional adapters are implemented and tested.
    |
    | Provider timestamps remain canonical UTC. User interfaces may localise
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
            'enabled' => true,
            'status' => 'ACTIVE',
        ],
        'DERIV' => [
            'label' => 'Deriv',
            'enabled' => true,
            'status' => 'ACTIVE',
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
            'binance_interval' => '5m',
            'deriv_granularity' => 300,
        ],
        'M15' => [
            'label' => '15 Minutes',
            'provider_interval' => '15min',
            'binance_interval' => '15m',
            'deriv_granularity' => 900,
        ],
        'H1' => [
            'label' => '1 Hour',
            'provider_interval' => '1h',
            'binance_interval' => '1h',
            'deriv_granularity' => 3600,
        ],
        'H4' => [
            'label' => '4 Hours',
            'provider_interval' => '4h',
            'binance_interval' => '4h',
            'deriv_granularity' => 14400,
        ],
        'D1' => [
            'label' => '1 Day',
            'provider_interval' => '1day',
            'binance_interval' => '1d',
            'deriv_granularity' => 86400,
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

    'crypto' => [
        'symbols' => [
            [
                'symbol' => 'BTCUSDT',
                'provider_symbol' => 'BTCUSDT',
                'name' => 'Bitcoin / Tether',
                'base_currency' => 'BTC',
                'quote_currency' => 'USDT',
                'enabled' => true,
            ],
            [
                'symbol' => 'ETHUSDT',
                'provider_symbol' => 'ETHUSDT',
                'name' => 'Ethereum / Tether',
                'base_currency' => 'ETH',
                'quote_currency' => 'USDT',
                'enabled' => true,
            ],
            [
                'symbol' => 'BNBUSDT',
                'provider_symbol' => 'BNBUSDT',
                'name' => 'BNB / Tether',
                'base_currency' => 'BNB',
                'quote_currency' => 'USDT',
                'enabled' => true,
            ],
            [
                'symbol' => 'SOLUSDT',
                'provider_symbol' => 'SOLUSDT',
                'name' => 'Solana / Tether',
                'base_currency' => 'SOL',
                'quote_currency' => 'USDT',
                'enabled' => true,
            ],
            [
                'symbol' => 'XRPUSDT',
                'provider_symbol' => 'XRPUSDT',
                'name' => 'XRP / Tether',
                'base_currency' => 'XRP',
                'quote_currency' => 'USDT',
                'enabled' => true,
            ],
            [
                'symbol' => 'ADAUSDT',
                'provider_symbol' => 'ADAUSDT',
                'name' => 'Cardano / Tether',
                'base_currency' => 'ADA',
                'quote_currency' => 'USDT',
                'enabled' => true,
            ],
            [
                'symbol' => 'DOGEUSDT',
                'provider_symbol' => 'DOGEUSDT',
                'name' => 'Dogecoin / Tether',
                'base_currency' => 'DOGE',
                'quote_currency' => 'USDT',
                'enabled' => true,
            ],
            [
                'symbol' => 'AVAXUSDT',
                'provider_symbol' => 'AVAXUSDT',
                'name' => 'Avalanche / Tether',
                'base_currency' => 'AVAX',
                'quote_currency' => 'USDT',
                'enabled' => true,
            ],
            [
                'symbol' => 'LINKUSDT',
                'provider_symbol' => 'LINKUSDT',
                'name' => 'Chainlink / Tether',
                'base_currency' => 'LINK',
                'quote_currency' => 'USDT',
                'enabled' => true,
            ],
            [
                'symbol' => 'TRXUSDT',
                'provider_symbol' => 'TRXUSDT',
                'name' => 'TRON / Tether',
                'base_currency' => 'TRX',
                'quote_currency' => 'USDT',
                'enabled' => true,
            ],
        ],
    ],

    'deriv' => [
        'symbols' => [
            [
                'symbol' => 'R_10',
                'provider_symbol' => 'R_10',
                'name' => 'Volatility 10 Index',
                'base_currency' => '',
                'quote_currency' => '',
                'enabled' => true,
            ],
            [
                'symbol' => 'R_25',
                'provider_symbol' => 'R_25',
                'name' => 'Volatility 25 Index',
                'base_currency' => '',
                'quote_currency' => '',
                'enabled' => true,
            ],
            [
                'symbol' => 'R_50',
                'provider_symbol' => 'R_50',
                'name' => 'Volatility 50 Index',
                'base_currency' => '',
                'quote_currency' => '',
                'enabled' => true,
            ],
            [
                'symbol' => 'R_75',
                'provider_symbol' => 'R_75',
                'name' => 'Volatility 75 Index',
                'base_currency' => '',
                'quote_currency' => '',
                'enabled' => true,
            ],
            [
                'symbol' => 'R_100',
                'provider_symbol' => 'R_100',
                'name' => 'Volatility 100 Index',
                'base_currency' => '',
                'quote_currency' => '',
                'enabled' => true,
            ],
            [
                'symbol' => '1HZ10V',
                'provider_symbol' => '1HZ10V',
                'name' => 'Volatility 10 (1s) Index',
                'base_currency' => '',
                'quote_currency' => '',
                'enabled' => true,
            ],
            [
                'symbol' => '1HZ25V',
                'provider_symbol' => '1HZ25V',
                'name' => 'Volatility 25 (1s) Index',
                'base_currency' => '',
                'quote_currency' => '',
                'enabled' => true,
            ],
            [
                'symbol' => '1HZ50V',
                'provider_symbol' => '1HZ50V',
                'name' => 'Volatility 50 (1s) Index',
                'base_currency' => '',
                'quote_currency' => '',
                'enabled' => true,
            ],
            [
                'symbol' => '1HZ75V',
                'provider_symbol' => '1HZ75V',
                'name' => 'Volatility 75 (1s) Index',
                'base_currency' => '',
                'quote_currency' => '',
                'enabled' => true,
            ],
            [
                'symbol' => '1HZ100V',
                'provider_symbol' => '1HZ100V',
                'name' => 'Volatility 100 (1s) Index',
                'base_currency' => '',
                'quote_currency' => '',
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
        'binance' => [
            'enabled' => env(
                'TRADEX_BINANCE_MARKET_DATA_ENABLED',
                true
            ),
            'base_url' => env(
                'TRADEX_BINANCE_MARKET_DATA_BASE_URL',
                'https://data-api.binance.vision'
            ),
            'timeout_seconds' => env(
                'TRADEX_BINANCE_MARKET_DATA_TIMEOUT_SECONDS',
                10
            ),
            'connect_timeout_seconds' => env(
                'TRADEX_BINANCE_MARKET_DATA_CONNECT_TIMEOUT_SECONDS',
                5
            ),
            'output_size' => env(
                'TRADEX_BINANCE_MARKET_DATA_OUTPUT_SIZE',
                260
            ),
        ],
        'binance_futures' => [
            'enabled' => env('TRADEX_BINANCE_FUTURES_MARKET_DATA_ENABLED', true),
            'base_url' => env('TRADEX_BINANCE_FUTURES_MARKET_DATA_BASE_URL', 'https://fapi.binance.com'),
            'timeout_seconds' => env('TRADEX_BINANCE_FUTURES_MARKET_DATA_TIMEOUT_SECONDS', 10),
            'connect_timeout_seconds' => env('TRADEX_BINANCE_FUTURES_MARKET_DATA_CONNECT_TIMEOUT_SECONDS', 5),
            'output_size' => env('TRADEX_BINANCE_FUTURES_MARKET_DATA_OUTPUT_SIZE', 260),
        ],
        'deriv' => [
    'enabled' => env(
        'TRADEX_CTRADER_DERIV_ENABLED',
        false
    ),

    'base_url' => env(
        'TRADEX_CTRADER_DERIV_BASE_URL',
        'http://127.0.0.1:8001'
    ),

    'timeout_seconds' => env(
        'TRADEX_CTRADER_DERIV_TIMEOUT_SECONDS',
        20
    ),

    'connect_timeout_seconds' => env(
        'TRADEX_CTRADER_DERIV_CONNECT_TIMEOUT_SECONDS',
        5
    ),

    'output_size' => env(
        'TRADEX_CTRADER_DERIV_OUTPUT_SIZE',
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
