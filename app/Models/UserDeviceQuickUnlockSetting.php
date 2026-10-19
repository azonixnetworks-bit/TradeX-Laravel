<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDeviceQuickUnlockSetting extends Model
{
    public const METHOD_PIN = 'PIN';

    public const METHOD_BIOMETRIC = 'BIOMETRIC';

    public const METHOD_BOTH = 'BOTH';

    public const METHODS = [
        self::METHOD_PIN,
        self::METHOD_BIOMETRIC,
        self::METHOD_BOTH,
    ];

    protected $fillable = [
        'user_device_id',
        'enabled',
        'method',
        'trusted_at_snapshot',
        'enrolled_at',
        'disabled_at',
        'credential_id',
        'credential_algorithm',
        'credential_public_key',
        'credential_rotated_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'trusted_at_snapshot' => 'datetime',
            'enrolled_at' => 'datetime',
            'disabled_at' => 'datetime',
            'credential_rotated_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(
            UserDevice::class,
            'user_device_id'
        );
    }
}
