<?php

$broker = 'Binance';
$verifiedAt = '2026-09-15';
$source = 'Binance official USDⓈ-M Futures calculator formula + Binance public exchangeInfo trading-rule documentation; live symbol filters and leverage tiers remain authoritative';

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
        // Risk planning precision only. Binance live exchangeInfo LOT_SIZE / MARKET_LOT_SIZE
        // filters are authoritative for executable min/max/step quantity.
        'min_lot' => 0.00000001,
        'max_lot' => 1000000000.0,
        'lot_step' => 0.00000001,
        'tick_size' => 0.00000001,
        'distance_unit' => 0.00000001,
        'distance_label' => 'price units',
        'spec_profile' => 'Binance USDⓈ-M perpetual risk-planning profile',
        'spec_status' => 'PARTIALLY VERIFIED',
        'spec_source' => $source,
        'spec_verified_at' => $verifiedAt,
    ];
}

return [
    'enabled' => true,
    'label' => 'Binance Futures',
    'description' => 'USDⓈ-M perpetual risk planning with Risk Based or Margin Based sizing. Selected leverage changes required margin, while live Binance leverage tiers remain symbol/notional dependent.',
    'broker' => $broker,
    'instrument_count' => count($instruments),
    'spec_status' => 'PARTIALLY VERIFIED',
    'spec_source' => $source,
    'spec_verified_at' => $verifiedAt,
    'calculation_modes' => [
        'RISK_BASED' => 'Risk Based',
        'MARGIN_BASED' => 'Margin Based',
    ],
    'leverage' => [
        'min' => 1,
        'max' => 125,
        'default' => 10,
        'tier_dependent' => true,
    ],
    'instruments' => $instruments,
];
