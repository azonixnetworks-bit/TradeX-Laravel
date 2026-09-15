<?php

$currencyNames = [
    'AUD' => 'Australian Dollar',
    'CAD' => 'Canadian Dollar',
    'CHF' => 'Swiss Franc',
    'JPY' => 'Japanese Yen',
    'NZD' => 'New Zealand Dollar',
    'EUR' => 'Euro',
    'GBP' => 'British Pound',
    'USD' => 'United States Dollar',
    'SGD' => 'Singapore Dollar',
    'DKK' => 'Danish Krone',
    'HKD' => 'Hong Kong Dollar',
    'NOK' => 'Norwegian Krone',
    'PLN' => 'Polish Zloty',
    'SEK' => 'Swedish Krona',
    'TRY' => 'Turkish Lira',
    'ZAR' => 'South African Rand',
    'CNH' => 'Chinese Yuan Offshore',
    'CZK' => 'Czech Koruna',
    'HUF' => 'Hungarian Forint',
    'MXN' => 'Mexican Peso',
    'RUB' => 'Russian Ruble',
    'THB' => 'Thai Baht',
];

$pairGroups = [
    'Major' => [
        'AUDUSD',
        'EURUSD',
        'GBPUSD',
        'USDCAD',
        'USDCHF',
        'USDJPY',
    ],
    'Minor' => [
        'AUDCAD',
        'AUDCHF',
        'AUDJPY',
        'AUDNZD',
        'CADCHF',
        'CADJPY',
        'CHFJPY',
        'EURAUD',
        'EURCAD',
        'EURCHF',
        'EURGBP',
        'EURJPY',
        'EURNZD',
        'GBPAUD',
        'GBPCAD',
        'GBPCHF',
        'GBPJPY',
        'GBPNZD',
        'NZDCAD',
        'NZDCHF',
        'NZDJPY',
        'NZDUSD',
    ],
    'Exotic' => [
        'AUDSGD',
        'CHFSGD',
        'EURDKK',
        'EURHKD',
        'EURNOK',
        'EURPLN',
        'EURSEK',
        'EURSGD',
        'EURTRY',
        'EURZAR',
        'GBPDKK',
        'GBPNOK',
        'GBPSEK',
        'GBPSGD',
        'GBPTRY',
        'NOKJPY',
        'NOKSEK',
        'SEKJPY',
        'SGDJPY',
        'USDCNH',
        'USDCZK',
        'USDDKK',
        'USDHKD',
        'USDHUF',
        'USDMXN',
        'USDNOK',
        'USDPLN',
        'USDRUB',
        'USDSEK',
        'USDTHB',
        'USDTRY',
        'USDZAR',
        'USDSGD',
    ],
];

$instruments = [];

foreach ($pairGroups as $category => $symbols) {
    foreach ($symbols as $symbol) {
        $base = substr($symbol, 0, 3);
        $quote = substr($symbol, 3, 3);
        $isJpyQuote = $quote === 'JPY';

        $instruments[$symbol] = [
            'name' => ($currencyNames[$base] ?? $base).' / '.($currencyNames[$quote] ?? $quote),
            'contract_size' => 100000.0,
            'base_currency' => $base,
            'quote_currency' => $quote,
            'min_lot' => 0.01,
            'max_lot' => $category === 'Exotic' ? 50.0 : 200.0,
            'lot_step' => 0.01,
            'tick_size' => $isJpyQuote ? 0.001 : 0.00001,
            'distance_unit' => $isJpyQuote ? 0.01 : 0.0001,
            'distance_label' => 'pips',
            'spec_profile' => 'IC Markets '.$category.' Forex',
            'spec_status' => 'PARTIALLY VERIFIED',
            'spec_source' => 'IC official Forex spreads table + Forex product specification sheet',
            'spec_verified_at' => '2026-09-15',
            'broker' => 'IC Markets',
            'category' => $category,
            'provider_symbol' => $base.'/'.$quote,
        ];
    }
}

ksort($instruments);

return [
    'enabled' => true,
    'broker' => 'IC Markets',
    'label' => 'Forex',
    'description' => 'IC Markets Forex catalog with 61 current Major, Minor and Exotic currency pairs. Cross-pair USD risk conversion uses TradeX market data when configured, with manual conversion-rate fallback.',
    'spec_status' => 'PARTIALLY VERIFIED',
    'spec_source' => 'IC official Forex spreads table + Forex product specification sheet',
    'spec_verified_at' => '2026-09-15',
    'instrument_count' => count($instruments),
    'instruments' => $instruments,
];
