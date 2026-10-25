<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'telegram_username',
        'profile_photo_path',
        'profile_photo_mime_type',
        'profile_photo_size_bytes',
        'profile_photo_sha256',
        'profile_photo_version',
        'profile_photo_updated_at',
        'paypal_email',
        'binance_email',
        'usdt_trc20_address',
        'bank_name',
        'bank_branch',
        'bank_account_holder',
        'bank_account_number',
        'financial_updated_at',
    ];

    protected $hidden = [
        'paypal_email',
        'binance_email',
        'usdt_trc20_address',
        'bank_name',
        'bank_branch',
        'bank_account_holder',
        'bank_account_number',
    ];

    protected function casts(): array
    {
        return [
            'telegram_username' => 'encrypted',
            'profile_photo_size_bytes' => 'integer',
            'profile_photo_version' => 'integer',
            'profile_photo_updated_at' => 'datetime',
            'paypal_email' => 'encrypted',
            'binance_email' => 'encrypted',
            'usdt_trc20_address' => 'encrypted',
            'bank_name' => 'encrypted',
            'bank_branch' => 'encrypted',
            'bank_account_holder' => 'encrypted',
            'bank_account_number' => 'encrypted',
            'financial_updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }
}
