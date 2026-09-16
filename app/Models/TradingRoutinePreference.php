<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradingRoutinePreference extends Model
{
    protected $fillable = [
        'user_id',
        'enabled',
        'timezone',
        'delivery_time_local',
        'planning_balance',
        'risk_per_trade_percent',
        'daily_risk_cap_percent',
        'max_trades',
        'max_consecutive_losses',
        'minimum_rr',
        'markets_json',
        'sessions_json',
        'pre_market_checklist_json',
        'no_trade_rules_json',
        'focus_statement',
        'whatsapp_pdf_enabled',
        'whatsapp_opted_in_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'planning_balance' => 'decimal:2',
            'risk_per_trade_percent' => 'decimal:4',
            'daily_risk_cap_percent' => 'decimal:4',
            'max_trades' => 'integer',
            'max_consecutive_losses' => 'integer',
            'minimum_rr' => 'decimal:4',
            'markets_json' => 'array',
            'sessions_json' => 'array',
            'pre_market_checklist_json' => 'array',
            'no_trade_rules_json' => 'array',
            'whatsapp_pdf_enabled' => 'boolean',
            'whatsapp_opted_in_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
