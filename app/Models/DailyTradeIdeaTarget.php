<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyTradeIdeaTarget extends Model
{
    protected $fillable = [
        'daily_trade_idea_id',
        'sort_order',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'price' => 'decimal:10',
        ];
    }

    public function dailyTradeIdea(): BelongsTo
    {
        return $this->belongsTo(DailyTradeIdea::class);
    }
}
