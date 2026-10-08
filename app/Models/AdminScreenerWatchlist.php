<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminScreenerWatchlist extends Model
{
    use HasFactory;

    public const MAX_SYMBOLS = 30;

    protected $table = 'admin_screener_watchlists';

    protected $fillable = [
        'admin_user_id',
        'market',
        'symbol',
        'name',
        'base_currency',
        'logo_url',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id');
    }
}
