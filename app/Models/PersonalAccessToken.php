<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    public function device(): BelongsTo
    {
        return $this->belongsTo(
            UserDevice::class,
            'user_device_id'
        );
    }

    public function isDeviceBound(): bool
    {
        return $this->user_device_id !== null;
    }

    public function hasSessionIdentity(): bool
    {
        return filled($this->session_uuid);
    }

    public function isTradeXBound(): bool
    {
        return $this->isDeviceBound()
            && $this->hasSessionIdentity();
    }
}