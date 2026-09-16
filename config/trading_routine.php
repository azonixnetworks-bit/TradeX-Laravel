<?php

return [
    'enabled' => (bool) env('TRADEX_TRADING_ROUTINE_ENABLED', true),

    'defaults' => [
        'timezone' => 'Asia/Colombo',
        'delivery_time_local' => '06:00',
        'planning_balance' => 10000.0,
        'risk_per_trade_percent' => 0.50,
        'daily_risk_cap_percent' => 1.00,
        'max_trades' => 3,
        'max_consecutive_losses' => 2,
        'minimum_rr' => 2.0,
        'markets' => ['FOREX'],
        'sessions' => ['LONDON', 'NEW_YORK'],
    ],

    'guardrails' => [
        'max_risk_per_trade_percent' => 1.00,
        'max_daily_risk_cap_percent' => 2.00,
        'max_trades' => 5,
        'max_consecutive_losses' => 3,
        'minimum_rr_floor' => 1.0,
    ],

    'default_pre_market_checklist' => [
        'Check sleep, focus and emotional state before opening charts.',
        'Review the planned market and session only.',
        'Mark higher-timeframe bias and key levels before looking for entries.',
        'Check scheduled high-impact news manually with your trusted calendar.',
        'Define entry, stop loss and take profit before placing any trade.',
    ],

    'default_no_trade_rules' => [
        'Do not trade outside the selected session unless it is part of the written plan.',
        'Do not increase risk after a loss or move a stop loss to widen risk.',
        'Stop after the configured consecutive-loss limit or daily risk cap is reached.',
        'Skip the setup when the minimum R:R cannot be achieved.',
        'Do not revenge trade, chase missed entries or add unplanned positions.',
    ],

    'default_focus_statement' => 'Protect capital first. Follow the plan, wait for quality setups and accept that no trade is also a valid decision.',

    'scheduler' => [
        'delivery_window_minutes' => (int) env('TRADEX_TRADING_ROUTINE_DELIVERY_WINDOW_MINUTES', 180),
        'batch_limit' => (int) env('TRADEX_TRADING_ROUTINE_SWEEP_LIMIT', 250),
        'max_delivery_attempts' => (int) env('TRADEX_TRADING_ROUTINE_MAX_DELIVERY_ATTEMPTS', 3),
        'retry_after_minutes' => (int) env('TRADEX_TRADING_ROUTINE_RETRY_AFTER_MINUTES', 15),
    ],

    'whatsapp' => [
        'driver' => env('TRADEX_TRADING_ROUTINE_WHATSAPP_DRIVER', 'disabled'),
        'template_name' => env('META_WHATSAPP_TRADING_ROUTINE_TEMPLATE_NAME'),
        'template_language' => env('META_WHATSAPP_TRADING_ROUTINE_TEMPLATE_LANGUAGE', 'en_US'),
    ],

    'disclaimer' => 'TradeX Daily Trading Routine is a discipline and risk-planning tool only. It does not provide a trade signal, guarantee profit, or replace your own market analysis. Verify market conditions, economic events, broker or prop-firm rules and all order details before trading.',
];
