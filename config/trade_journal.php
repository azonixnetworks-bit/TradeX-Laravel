<?php

return [
    'markets' => ['forex', 'crypto', 'deriv', 'stocks'],
    'stock_regions' => ['sri_lanka_cse', 'india', 'international'],
    'directions' => ['buy', 'sell'],
    'statuses' => ['planned', 'open', 'closed', 'cancelled'],
    'results' => ['win', 'loss', 'breakeven', 'partial'],
    'timeframes' => ['M1', 'M5', 'M15', 'M30', 'H1', 'H4', 'D1', 'W1'],

    'emotions_before' => [
        'calm', 'confident', 'fear', 'fomo', 'greed', 'revenge',
        'overconfidence', 'hesitant', 'frustrated', 'tired', 'distracted', 'other',
    ],

    'emotions_after' => [
        'calm', 'satisfied', 'relieved', 'disappointed', 'frustrated',
        'angry', 'revenge_feeling', 'overconfident', 'neutral', 'other',
    ],

    'followed_plan_after' => ['yes', 'partially', 'no'],

    'mistake_tags' => [
        'early_entry', 'late_entry', 'wrong_entry', 'moved_stop_loss',
        'removed_stop_loss', 'early_exit', 'late_exit', 'overtrading',
        'fomo', 'revenge_trade', 'oversized_position', 'ignored_plan',
        'ignored_news', 'poor_risk_management', 'wrong_session',
        'no_confirmation', 'emotional_entry', 'emotional_exit', 'other',
    ],

    'checklist_values' => ['yes', 'no', 'na'],

    /*
    | Discipline limits are analytics/warning thresholds only. They must never
    | block a journal record because the journal must preserve what happened.
    */
    'discipline' => [
        'max_trades_per_day' => 3,
        'stop_after_wins' => 2,
    ],

    /*
    | RR is calculated for review and analytics only. There is intentionally
    | no minimum RR rule or validation threshold in Trade Journal V1.
    */
    'screenshots' => [
        'disk' => 'private',
        'max_kilobytes' => 10240,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
    ],
];
