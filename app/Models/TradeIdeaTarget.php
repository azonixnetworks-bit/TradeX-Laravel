<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeIdeaTarget extends Model
{
    protected $fillable = [
        'trade_idea_id',
        'label',
        'price',
        'sort_order',
        'hit_at',
        'hit_price',
        'hit_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:10',
            'hit_price' => 'decimal:10',
            'sort_order' => 'integer',
            'hit_at' => 'datetime',
        ];
    }

    public function tradeIdea(): BelongsTo
    {
        return $this->belongsTo(TradeIdea::class);
    }

    public function hitByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'hit_by_admin_id');
    }

    public function isHit(): bool
    {
        return $this->hit_at !== null;
    }
}
