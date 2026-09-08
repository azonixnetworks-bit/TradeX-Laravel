<?php

return [
    /*
    |--------------------------------------------------------------------------
    | TradeX Forex Screener — cTrader Open API / IC Markets
    |--------------------------------------------------------------------------
    |
    | The cTrader Open API is broker-affiliated rather than an anonymous public
    | feed. TradeX therefore requires an approved Open API application plus a
    | view-authorised cTID trading account. Secrets stay in deployment secrets.
    |
    | Demo is the safe default. Live and demo Open API endpoints are isolated.
    | JSON over secure WebSocket uses port 5036.
    |
    */

    'enabled' => env('TRADEX_CTRADER_SCREENER_ENABLED', false),

    'environment' => strtoupper((string) env(
        'TRADEX_CTRADER_ENVIRONMENT',
        'DEMO'
    )),

    'client_id' => env('TRADEX_CTRADER_CLIENT_ID', ''),
    'client_secret' => env('TRADEX_CTRADER_CLIENT_SECRET', ''),
    'access_token' => env('TRADEX_CTRADER_ACCESS_TOKEN', ''),

    // This is the ctidTraderAccountId used by Open API messages, not the
    // human-facing broker login number shown in cTrader.
    'ctid_trader_account_id' => env(
        'TRADEX_CTRADER_CTID_TRADER_ACCOUNT_ID',
        ''
    ),

    'demo_websocket_url' => env(
        'TRADEX_CTRADER_DEMO_WEBSOCKET_URL',
        'wss://demo.ctraderapi.com:5036/'
    ),
    'live_websocket_url' => env(
        'TRADEX_CTRADER_LIVE_WEBSOCKET_URL',
        'wss://live.ctraderapi.com:5036/'
    ),

    'allowed_hosts' => [
        'demo.ctraderapi.com',
        'live.ctraderapi.com',
    ],

    'timeout_seconds' => env(
        'TRADEX_CTRADER_TIMEOUT_SECONDS',
        12
    ),
    'connect_timeout_seconds' => env(
        'TRADEX_CTRADER_CONNECT_TIMEOUT_SECONDS',
        6
    ),
    'max_handshake_bytes' => 16384,
    'max_response_bytes' => 4194304,
    'output_size' => env(
        'TRADEX_CTRADER_OUTPUT_SIZE',
        260
    ),

    // cTrader historical data is limited to 5 requests/sec/connection.
    // 225 ms keeps TradeX below that ceiling with a small safety margin.
    'historical_request_spacing_ms' => env(
        'TRADEX_CTRADER_HISTORICAL_REQUEST_SPACING_MS',
        225
    ),

    'symbol_map_cache_seconds' => env(
        'TRADEX_CTRADER_SYMBOL_MAP_CACHE_SECONDS',
        21600
    ),

    // If cTrader is not configured, or a temporary cTrader provider error is
    // encountered, retain the existing Twelve Data Forex adapter as a bounded
    // fallback. Set false in production if a strict broker-feed-only policy is
    // required.
    'fallback_to_twelve_data' => env(
        'TRADEX_CTRADER_FALLBACK_TWELVE_DATA',
        true
    ),

    // Broker-specific symbol names may differ. Optional JSON object example:
    // {"EUR/USD":"EURUSD","XAU/USD":"XAUUSD"}
    'symbol_overrides' => (static function (): array {
        $decoded = json_decode(
            (string) env('TRADEX_CTRADER_SYMBOL_OVERRIDES_JSON', '{}'),
            true
        );

        return is_array($decoded)
            ? $decoded
            : [];
    })(),
];
