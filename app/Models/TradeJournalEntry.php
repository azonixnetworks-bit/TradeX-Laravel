<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TradeJournalEntry extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'market', 'stock_region', 'symbol', 'direction', 'status',
        'trade_at', 'user_timezone', 'session_label', 'session_keys',
        'session_source', 'broker', 'account_label', 'setup_strategy',
        'timeframes', 'entry_reason', 'notes_before',
        'before_screenshot_path', 'before_screenshot_mime',
        'planned_entry', 'actual_entry', 'planned_sl', 'current_sl',
        'planned_tp', 'current_tp', 'position_size', 'risk_amount',
        'risk_currency', 'risk_percent', 'planned_rr', 'emotion_before',
        'emotion_before_other', 'confidence_before', 'mental_state_note',
        'checklist', 'opened_at', 'execution_note', 'exit_price', 'exit_at',
        'result', 'gross_pnl', 'fees', 'net_pnl', 'pnl_currency',
        'pnl_percent', 'realized_r', 'after_screenshot_path',
        'after_screenshot_mime', 'emotion_after', 'emotion_after_other',
        'followed_plan_after', 'mistake_tags', 'went_well', 'went_wrong',
        'lesson_learned', 'improvement_next_trade', 'rating',
        'cancellation_reason', 'cancelled_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'trade_at' => 'immutable_datetime',
            'opened_at' => 'immutable_datetime',
            'exit_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'session_keys' => 'array',
            'timeframes' => 'array',
            'checklist' => 'array',
            'mistake_tags' => 'array',
            'planned_entry' => 'decimal:12',
            'actual_entry' => 'decimal:12',
            'planned_sl' => 'decimal:12',
            'current_sl' => 'decimal:12',
            'planned_tp' => 'decimal:12',
            'current_tp' => 'decimal:12',
            'position_size' => 'decimal:12',
            'risk_amount' => 'decimal:8',
            'risk_percent' => 'decimal:4',
            'planned_rr' => 'decimal:8',
            'gross_pnl' => 'decimal:8',
            'fees' => 'decimal:8',
            'net_pnl' => 'decimal:8',
            'pnl_percent' => 'decimal:6',
            'realized_r' => 'decimal:8',
            'confidence_before' => 'integer',
            'rating' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPlanned(): bool
    {
        return $this->status === 'planned';
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
