<?php

return [
    /*
    |--------------------------------------------------------------------------
    | TradeX planning guardrails
    |--------------------------------------------------------------------------
    |
    | These are TradeX risk-planning limits, not prop-firm rules. Firm rules
    | are entered explicitly for each report because they vary by provider,
    | program, phase, platform and time.
    |
    */
    'max_recommended_risk_percent' => 0.50,
    'max_daily_plan_percent' => 1.00,
    'max_planned_entries' => 5,
    'safety_reserve_percent' => 20.0,

    'status_thresholds' => [
        'caution_used_percent' => 50.0,
        'danger_used_percent' => 75.0,
    ],

    // Risk is reduced after losses. It is never increased after a loss.
    'loss_sequence_factors' => [1.00, 0.80, 0.60, 0.50],

    'disclaimer' => 'TradeX Funded Account Advisor is a risk-planning reference only. It does not place trades, guarantee challenge completion or replace the current official rules of your funded-account provider. Always verify daily reset time, drawdown basis, trailing rules, consistency rules, news restrictions and payout conditions directly with the provider before trading.',

    'affiliate_disclosure' => 'TradeX may receive a referral commission if you open an account through a partner link. Partner selection and commission do not change the advisor calculation or risk recommendation.',
];
