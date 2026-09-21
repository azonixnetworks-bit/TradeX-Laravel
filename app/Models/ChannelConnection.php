<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelConnection extends Model
{
    public const PLATFORM_TELEGRAM = 'TELEGRAM';

    public const TEST_UNTESTED = 'UNTESTED';
    public const TEST_SUCCESS = 'SUCCESS';
    public const TEST_FAILED = 'FAILED';

    protected $fillable = [
        'platform',
        'is_enabled',
        'credentials',
        'settings',
        'metadata',
        'last_test_status',
        'last_test_message',
        'last_tested_at',
        'version',
        'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'metadata' => 'array',
            'last_tested_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'updated_by_admin_id'
        );
    }

    public function isTestedSuccessfully(): bool
    {
        return $this->last_test_status === self::TEST_SUCCESS;
    }
}
