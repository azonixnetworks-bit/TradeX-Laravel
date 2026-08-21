<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeIdeaUpdate extends Model
{
    public const TYPE_NOTE = 'NOTE';
    public const TYPE_ENTRY_TRIGGERED = 'ENTRY_TRIGGERED';
    public const TYPE_STOP_MOVED = 'STOP_MOVED';
    public const TYPE_TARGET_HIT = 'TARGET_HIT';
    public const TYPE_CLOSED = 'CLOSED';

    protected $fillable = [
        'trade_idea_id',
        'trade_idea_target_id',
        'type',
        'message',
        'price',
        'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:10',
        ];
    }

    public function tradeIdea(): BelongsTo
    {
        return $this->belongsTo(TradeIdea::class);
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(TradeIdeaTarget::class, 'trade_idea_target_id');
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by_admin_id');
    }
}
