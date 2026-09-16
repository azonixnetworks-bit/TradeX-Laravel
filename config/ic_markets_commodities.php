<?php

$source = 'IC official Commodity/Futures product specifications reviewed 2026-09-15';
$broker = 'IC Markets';
$verifiedAt = '2026-09-15';

$rows = [
    // Precious metals: symbol => [name, category, contract, min, max, step, tick, base, quote]
    'XAUUSD' => ['Gold / US Dollar', 'Precious Metals', 100.0, 0.01, 100.0, 0.01, 0.01, 'XAU', 'USD'],
    'XAUEUR' => ['Gold / Euro', 'Precious Metals', 100.0, 0.01, 100.0, 0.01, 0.01, 'XAU', 'EUR'],
    'XAUAUD' => ['Gold / Australian Dollar', 'Precious Metals', 100.0, 0.01, 100.0, 0.01, 0.01, 'XAU', 'AUD'],
    'XAUJPY' => ['Gold / Japanese Yen', 'Precious Metals', 100.0, 0.01, 100.0, 0.01, 0.01, 'XAU', 'JPY'],
    'XAUGBP' => ['Gold / British Pound', 'Precious Metals', 100.0, 0.01, 100.0, 0.01, 0.01, 'XAU', 'GBP'],
    'XAUCHF' => ['Gold / Swiss Franc', 'Precious Metals', 100.0, 0.01, 100.0, 0.01, 0.01, 'XAU', 'CHF'],
    'XAGUSD' => ['Silver / US Dollar', 'Precious Metals', 1000.0, 0.01, 100.0, 0.01, 0.001, 'XAG', 'USD'],
    'XAGEUR' => ['Silver / Euro', 'Precious Metals', 1000.0, 0.01, 100.0, 0.01, 0.001, 'XAG', 'EUR'],
    'XAGAUD' => ['Silver / Australian Dollar', 'Precious Metals', 1000.0, 0.01, 100.0, 0.01, 0.001, 'XAG', 'AUD'],
    'XPTUSD' => ['Platinum / US Dollar', 'Precious Metals', 100.0, 0.01, 100.0, 0.01, 0.01, 'XPT', 'USD'],
    'XPDUSD' => ['Palladium / US Dollar', 'Precious Metals', 100.0, 0.01, 100.0, 0.01, 0.01, 'XPD', 'USD'],

    // Spot energies.
    'XTIUSD' => ['WTI Crude Oil Spot / US Dollar', 'Spot Energy', 100.0, 0.50, 200.0, 0.01, 0.001, 'XTI', 'USD'],
    'XBRUSD' => ['Brent Crude Oil Spot / US Dollar', 'Spot Energy', 100.0, 0.50, 200.0, 0.01, 0.001, 'XBR', 'USD'],
    'XNGUSD' => ['Natural Gas Spot / US Dollar', 'Spot Energy', 10000.0, 0.50, 200.0, 0.01, 0.001, 'XNG', 'USD'],

    // Agriculture / soft commodities.
    'WHEAT' => ['Wheat Futures CFD', 'Agriculture', 4.0, 1.0, 200.0, 0.01, 0.01, 'WHEAT', 'USD'],
    'CORN' => ['Corn Futures CFD', 'Agriculture', 4.0, 1.0, 200.0, 0.01, 0.01, 'CORN', 'USD'],
    'SOYBEAN' => ['Soybean Futures CFD', 'Agriculture', 4.0, 1.0, 200.0, 0.01, 0.01, 'SOYBEAN', 'USD'],
    'COFFEE' => ['Coffee Futures CFD', 'Agriculture', 10.0, 1.0, 200.0, 0.01, 0.01, 'COFFEE', 'USD'],
    'SUGAR' => ['Sugar Futures CFD', 'Agriculture', 100.0, 1.0, 200.0, 0.01, 0.01, 'SUGAR', 'USD'],
    'COTTON' => ['Cotton Futures CFD', 'Agriculture', 100.0, 1.0, 200.0, 0.01, 0.01, 'COTTON', 'USD'],
    'OJ' => ['Orange Juice Futures CFD', 'Agriculture', 20.0, 1.0, 200.0, 0.01, 0.01, 'OJ', 'USD'],
    'COCOA' => ['Cocoa Futures CFD', 'Agriculture', 1.0, 1.0, 200.0, 0.01, 0.01, 'COCOA', 'USD'],

    // Stable energy-futures symbols. Rolling gold-future aliases such as GC25 are intentionally excluded.
    'BRENT' => ['Brent Crude Oil Futures CFD', 'Energy Futures', 100.0, 1.0, 200.0, 1.0, 0.01, 'BRENT', 'USD'],
    'WTI' => ['WTI Crude Oil Futures CFD', 'Energy Futures', 100.0, 1.0, 200.0, 1.0, 0.01, 'WTI', 'USD'],
];

$instruments = [];

foreach ($rows as $symbol => $row) {
    [$name, $category, $contractSize, $minLot, $maxLot, $lotStep, $tickSize, $baseCurrency, $quoteCurrency] = $row;

    $instruments[$symbol] = [
        'name' => $name,
        'category' => $category,
        'broker' => $broker,
        'contract_size' => $contractSize,
        'base_currency' => $baseCurrency,
        'quote_currency' => $quoteCurrency,
        'min_lot' => $minLot,
        'max_lot' => $maxLot,
        'lot_step' => $lotStep,
        'tick_size' => $tickSize,
        'distance_unit' => $tickSize,
        'distance_label' => 'price units',
        'spec_profile' => 'IC Markets commodity CFD reference',
        'spec_status' => 'PARTIALLY VERIFIED',
        'spec_source' => $source,
        'spec_verified_at' => $verifiedAt,
    ];
}

return [
    'enabled' => true,
    'label' => 'Commodity',
    'description' => 'IC Markets metals, spot energies, agriculture and stable energy-futures CFD catalog. Cross-currency metals use TradeX quote-currency to USD conversion.',
    'broker' => $broker,
    'instrument_count' => count($instruments),
    'spec_status' => 'PARTIALLY VERIFIED',
    'spec_source' => $source,
    'spec_verified_at' => $verifiedAt,
    'instruments' => $instruments,
];
