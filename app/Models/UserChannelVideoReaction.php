<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserChannelVideoReaction extends Model
{
    public const LIKE = 'LIKE';
    public const DISLIKE = 'DISLIKE';

    protected $fillable = [
        'user_id',
        'channel_video_id',
        'reaction',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(ChannelVideo::class, 'channel_video_id');
    }
}
