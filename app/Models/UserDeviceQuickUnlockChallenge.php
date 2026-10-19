<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDeviceQuickUnlockChallenge extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'quick_unlock_setting_id',
        'nonce_hash',
        'failed_attempts',
        'expires_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'failed_attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(
            UserDeviceQuickUnlockSetting::class,
            'quick_unlock_setting_id'
        );
    }
}
