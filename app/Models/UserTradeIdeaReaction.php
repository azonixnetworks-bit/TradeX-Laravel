<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTradeIdeaReaction extends Model
{
    public const LIKE = 'LIKE';
    public const DISLIKE = 'DISLIKE';

    protected $fillable = ['user_id', 'trade_idea_id', 'reaction'];
}
