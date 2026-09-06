<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TradeJournalReview extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'period_type', 'period_start', 'period_end', 'user_timezone',
        'summary_snapshot', 'positive_reflection', 'negative_reflection',
        'biggest_lesson', 'stop_doing', 'continue_doing', 'next_focus',
        'biggest_achievement', 'biggest_weakness', 'habit_to_remove',
        'habit_to_continue', 'rating',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'immutable_date',
            'period_end' => 'immutable_date',
            'summary_snapshot' => 'array',
            'rating' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
