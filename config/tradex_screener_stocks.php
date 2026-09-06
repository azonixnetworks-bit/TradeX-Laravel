<?php

return [
    /*
    |--------------------------------------------------------------------------
    | TradeX SCREENER-04 — Stocks
    |--------------------------------------------------------------------------
    |
    | International (United States) uses Twelve Data real-time/historical
    | equities. India is exposed as EOD-only because the current Twelve Data
    | NSE/BSE coverage is EOD. Sri Lanka is deliberately not scraped: CSE must
    | be connected through an approved/licensed market-data source before it is
    | activated for customer display.
    |
    */

    'enabled' => env('TRADEX_STOCKS_SCREENER_ENABLED', true),

    'regions' => [
        'SRI_LANKA' => [
            'label' => 'Sri Lanka',
            'country' => 'Sri Lanka',
            'enabled' => false,
            'status' => 'PROVIDER_REQUIRED',
            'data_mode' => 'LICENSED_PROVIDER_REQUIRED',
            'supported_timeframes' => [],
            'exchanges' => [
                'CSE' => [
                    'label' => 'Colombo Stock Exchange',
                    'mic_code' => 'XCOL',
                    'enabled' => false,
                ],
            ],
        ],
        'INDIA' => [
            'label' => 'India',
            'country' => 'India',
            'enabled' => true,
            'status' => 'ACTIVE_EOD',
            'data_mode' => 'EOD',
            'supported_timeframes' => ['D1'],
            'exchanges' => [
                'NSE' => [
                    'label' => 'National Stock Exchange of India',
                    'mic_code' => 'XNSE',
                    'enabled' => true,
                ],
                'BSE' => [
                    'label' => 'Bombay Stock Exchange',
                    'mic_code' => 'XBOM',
                    'enabled' => true,
                ],
            ],
        ],
        'INTERNATIONAL' => [
            'label' => 'International',
            'country' => 'United States',
            'enabled' => true,
            'status' => 'ACTIVE_REALTIME',
            'data_mode' => 'REALTIME',
            'supported_timeframes' => ['M5', 'M15', 'H1', 'H4', 'D1'],
            'exchanges' => [
                'NASDAQ' => [
                    'label' => 'NASDAQ',
                    'mic_code' => 'XNAS',
                    'enabled' => true,
                ],
                'NYSE' => [
                    'label' => 'New York Stock Exchange',
                    'mic_code' => 'XNYS',
                    'enabled' => true,
                ],
            ],
        ],
    ],

    'symbols' => [
        [
            'symbol' => 'AAPL',
            'provider_symbol' => 'AAPL',
            'name' => 'Apple Inc.',
            'region' => 'INTERNATIONAL',
            'exchange' => 'NASDAQ',
            'mic_code' => 'XNAS',
            'exchange_timezone' => 'America/New_York',
            'currency' => 'USD',
            'data_mode' => 'REALTIME',
            'supported_timeframes' => ['M5', 'M15', 'H1', 'H4', 'D1'],
            'enabled' => true,
        ],
        [
            'symbol' => 'MSFT',
            'provider_symbol' => 'MSFT',
            'name' => 'Microsoft Corporation',
            'region' => 'INTERNATIONAL',
            'exchange' => 'NASDAQ',
            'mic_code' => 'XNAS',
            'exchange_timezone' => 'America/New_York',
            'currency' => 'USD',
            'data_mode' => 'REALTIME',
            'supported_timeframes' => ['M5', 'M15', 'H1', 'H4', 'D1'],
            'enabled' => true,
        ],
        [
            'symbol' => 'NVDA',
            'provider_symbol' => 'NVDA',
            'name' => 'NVIDIA Corporation',
            'region' => 'INTERNATIONAL',
            'exchange' => 'NASDAQ',
            'mic_code' => 'XNAS',
            'exchange_timezone' => 'America/New_York',
            'currency' => 'USD',
            'data_mode' => 'REALTIME',
            'supported_timeframes' => ['M5', 'M15', 'H1', 'H4', 'D1'],
            'enabled' => true,
        ],
        [
            'symbol' => 'AMZN',
            'provider_symbol' => 'AMZN',
            'name' => 'Amazon.com, Inc.',
            'region' => 'INTERNATIONAL',
            'exchange' => 'NASDAQ',
            'mic_code' => 'XNAS',
            'exchange_timezone' => 'America/New_York',
            'currency' => 'USD',
            'data_mode' => 'REALTIME',
            'supported_timeframes' => ['M5', 'M15', 'H1', 'H4', 'D1'],
            'enabled' => true,
        ],
        [
            'symbol' => 'GOOGL',
            'provider_symbol' => 'GOOGL',
            'name' => 'Alphabet Inc. Class A',
            'region' => 'INTERNATIONAL',
            'exchange' => 'NASDAQ',
            'mic_code' => 'XNAS',
            'exchange_timezone' => 'America/New_York',
            'currency' => 'USD',
            'data_mode' => 'REALTIME',
            'supported_timeframes' => ['M5', 'M15', 'H1', 'H4', 'D1'],
            'enabled' => true,
        ],
        [
            'symbol' => 'META',
            'provider_symbol' => 'META',
            'name' => 'Meta Platforms, Inc.',
            'region' => 'INTERNATIONAL',
            'exchange' => 'NASDAQ',
            'mic_code' => 'XNAS',
            'exchange_timezone' => 'America/New_York',
            'currency' => 'USD',
            'data_mode' => 'REALTIME',
            'supported_timeframes' => ['M5', 'M15', 'H1', 'H4', 'D1'],
            'enabled' => true,
        ],
        [
            'symbol' => 'TSLA',
            'provider_symbol' => 'TSLA',
            'name' => 'Tesla, Inc.',
            'region' => 'INTERNATIONAL',
            'exchange' => 'NASDAQ',
            'mic_code' => 'XNAS',
            'exchange_timezone' => 'America/New_York',
            'currency' => 'USD',
            'data_mode' => 'REALTIME',
            'supported_timeframes' => ['M5', 'M15', 'H1', 'H4', 'D1'],
            'enabled' => true,
        ],
        [
            'symbol' => 'JPM',
            'provider_symbol' => 'JPM',
            'name' => 'JPMorgan Chase & Co.',
            'region' => 'INTERNATIONAL',
            'exchange' => 'NYSE',
            'mic_code' => 'XNYS',
            'exchange_timezone' => 'America/New_York',
            'currency' => 'USD',
            'data_mode' => 'REALTIME',
            'supported_timeframes' => ['M5', 'M15', 'H1', 'H4', 'D1'],
            'enabled' => true,
        ],
        [
            'symbol' => 'V',
            'provider_symbol' => 'V',
            'name' => 'Visa Inc.',
            'region' => 'INTERNATIONAL',
            'exchange' => 'NYSE',
            'mic_code' => 'XNYS',
            'exchange_timezone' => 'America/New_York',
            'currency' => 'USD',
            'data_mode' => 'REALTIME',
            'supported_timeframes' => ['M5', 'M15', 'H1', 'H4', 'D1'],
            'enabled' => true,
        ],
        [
            'symbol' => 'RELIANCE',
            'provider_symbol' => 'RELIANCE',
            'name' => 'Reliance Industries Limited',
            'region' => 'INDIA',
            'exchange' => 'NSE',
            'mic_code' => 'XNSE',
            'exchange_timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'data_mode' => 'EOD',
            'supported_timeframes' => ['D1'],
            'enabled' => true,
        ],
        [
            'symbol' => 'TCS',
            'provider_symbol' => 'TCS',
            'name' => 'Tata Consultancy Services Limited',
            'region' => 'INDIA',
            'exchange' => 'NSE',
            'mic_code' => 'XNSE',
            'exchange_timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'data_mode' => 'EOD',
            'supported_timeframes' => ['D1'],
            'enabled' => true,
        ],
        [
            'symbol' => 'HDFCBANK',
            'provider_symbol' => 'HDFCBANK',
            'name' => 'HDFC Bank Limited',
            'region' => 'INDIA',
            'exchange' => 'NSE',
            'mic_code' => 'XNSE',
            'exchange_timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'data_mode' => 'EOD',
            'supported_timeframes' => ['D1'],
            'enabled' => true,
        ],
        [
            'symbol' => 'INFY',
            'provider_symbol' => 'INFY',
            'name' => 'Infosys Limited',
            'region' => 'INDIA',
            'exchange' => 'NSE',
            'mic_code' => 'XNSE',
            'exchange_timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'data_mode' => 'EOD',
            'supported_timeframes' => ['D1'],
            'enabled' => true,
        ],
        [
            'symbol' => 'ICICIBANK',
            'provider_symbol' => 'ICICIBANK',
            'name' => 'ICICI Bank Limited',
            'region' => 'INDIA',
            'exchange' => 'NSE',
            'mic_code' => 'XNSE',
            'exchange_timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'data_mode' => 'EOD',
            'supported_timeframes' => ['D1'],
            'enabled' => true,
        ],
    ],

    'provider' => [
        'enabled' => env('TRADEX_STOCKS_TWELVE_DATA_ENABLED', true),
        'external_display_approved' => env(
            'TRADEX_STOCKS_EXTERNAL_DISPLAY_APPROVED',
            false
        ),
        'base_url' => env(
            'TRADEX_TWELVE_DATA_BASE_URL',
            'https://api.twelvedata.com'
        ),
        'api_key' => env('TRADEX_TWELVE_DATA_API_KEY', ''),
        'timeout_seconds' => env(
            'TRADEX_STOCKS_TWELVE_DATA_TIMEOUT_SECONDS',
            10
        ),
        'connect_timeout_seconds' => env(
            'TRADEX_STOCKS_TWELVE_DATA_CONNECT_TIMEOUT_SECONDS',
            5
        ),
        'output_size' => env(
            'TRADEX_STOCKS_TWELVE_DATA_OUTPUT_SIZE',
            260
        ),
    ],
];
