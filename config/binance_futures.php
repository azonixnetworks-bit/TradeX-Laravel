<?php

$broker = 'Binance';
$verifiedAt = '2026-09-15';
$source = 'Binance official USDⓈ-M Futures calculator, fee, funding, liquidation and exchangeInfo documentation; live symbol filters, fee tier, funding rate and leverage/maintenance-margin tiers remain authoritative';

// Emergency fallback only. Production/runtime symbol discovery comes from Binance public
// GET /fapi/v1/exchangeInfo and is cached server-side. Keep these core symbols so the
// calculator remains usable when Binance market-data access is temporarily unavailable.
$symbols = [
    'BTCUSDT' => 'Bitcoin / Tether',
    'ETHUSDT' => 'Ethereum / Tether',
    'BNBUSDT' => 'BNB / Tether',
    'SOLUSDT' => 'Solana / Tether',
    'XRPUSDT' => 'XRP / Tether',
    'DOGEUSDT' => 'Dogecoin / Tether',
    'ADAUSDT' => 'Cardano / Tether',
    'LINKUSDT' => 'Chainlink / Tether',
    'AVAXUSDT' => 'Avalanche / Tether',
    'LTCUSDT' => 'Litecoin / Tether',
];

$instruments = [];

foreach ($symbols as $symbol => $name) {
    $base = substr($symbol, 0, -4);

    $instruments[$symbol] = [
        'name' => $name,
        'category' => 'USDⓈ-M Perpetual',
        'broker' => $broker,
        'contract_size' => 1.0,
        'base_currency' => $base,
        'quote_currency' => 'USDT',
        // Fallback risk-planning precision only. Binance live exchangeInfo PRICE_FILTER,
        // LOT_SIZE and MARKET_LOT_SIZE filters are authoritative when live catalog loads.
        'min_lot' => 0.00000001,
        'max_lot' => 1000000000.0,
        'lot_step' => 0.00000001,
        'tick_size' => 0.00000001,
        'distance_unit' => 0.00000001,
        'distance_label' => 'price units',
        'spec_profile' => 'Binance USDⓈ-M perpetual emergency fallback profile',
        'spec_status' => 'FALLBACK / PARTIALLY VERIFIED',
        'spec_source' => $source,
        'spec_verified_at' => $verifiedAt,
    ];
}

return [
    'enabled' => true,
    'label' => 'Binance Futures',
    'description' => 'USDⓈ-M perpetual risk planning with live Binance symbol discovery, leverage, all-in fee/slippage/funding estimates and optional isolated liquidation estimate.',
    'broker' => $broker,
    'instrument_count' => count($instruments),
    'spec_status' => 'PARTIALLY VERIFIED',
    'spec_source' => $source,
    'spec_verified_at' => $verifiedAt,
    'live_catalog' => [
        'enabled' => (bool) env('BINANCE_FUTURES_LIVE_CATALOG_ENABLED', true),
        'base_url' => (string) env('BINANCE_FUTURES_BASE_URL', 'https://fapi.binance.com'),
        'quote_asset' => 'USDT',
        'contract_type' => 'PERPETUAL',
        'trading_status' => 'TRADING',
        'cache_seconds' => (int) env('BINANCE_FUTURES_CATALOG_CACHE_SECONDS', 600),
        'stale_cache_seconds' => (int) env('BINANCE_FUTURES_CATALOG_STALE_SECONDS', 86400),
        'connect_timeout_seconds' => (int) env('BINANCE_FUTURES_CONNECT_TIMEOUT_SECONDS', 2),
        'timeout_seconds' => (int) env('BINANCE_FUTURES_TIMEOUT_SECONDS', 5),
        // Tests remain deterministic by default. Dedicated tests can explicitly enable this.
        'allow_testing' => false,
    ],
    'calculation_modes' => [
        'RISK_BASED' => 'Risk Based',
        'MARGIN_BASED' => 'Margin Based',
    ],
    'margin_modes' => [
        'ISOLATED' => 'Isolated',
        'CROSS' => 'Cross',
    ],
    'leverage' => [
        'min' => 1,
        'max' => 125,
        'default' => 10,
        'tier_dependent' => true,
    ],
    'cost_assumptions' => [
        // Binance's current fee FAQ uses these regular-user rates as examples and explicitly
        // says fees can change. The UI exposes them as editable reference assumptions.
        'reference_maker_fee_percent' => 0.02,
        'reference_taker_fee_percent' => 0.05,
        'default_entry_fee_percent' => 0.05,
        'default_exit_fee_percent' => 0.05,
        'default_slippage_percent' => 0.0,
        'default_funding_rate_percent' => 0.0,
        'default_funding_intervals' => 0,
        'fee_source' => 'Binance Futures Fee Structure & Fee Calculations (reference regular-user example; verify live VIP/promotional fee)',
        'funding_source' => 'Binance Futures Funding Rates (live symbol rate and interval are authoritative)',
    ],
    'liquidation' => [
        'default_margin_mode' => 'ISOLATED',
        'requires_live_tier_inputs' => true,
        'source' => 'Binance USDⓈ-M liquidation formula. Isolated one-way estimate requires current MMR and Maintenance Amount from the live leverage/margin tier.',
    ],
    'instruments' => $instruments,
];
