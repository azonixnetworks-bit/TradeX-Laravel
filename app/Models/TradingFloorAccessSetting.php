<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradingFloorAccessSetting extends Model
{
    public const MODE_NONE = 'NONE';
    public const MODE_WARNING = 'WARNING';
    public const MODE_BLOCK_AFTER_GRACE = 'BLOCK_AFTER_GRACE';
    public const MODE_BLOCK_IMMEDIATE = 'BLOCK_IMMEDIATE';

    protected $fillable = [
        'commission_enforcement_mode',
        'block_after_overdue_days',
        'allow_exit_when_blocked',
        'require_active_client',
        'enforce_floor_match',
        'api_enabled',
        'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'block_after_overdue_days' => 'integer',
            'allow_exit_when_blocked' => 'boolean',
            'require_active_client' => 'boolean',
            'enforce_floor_match' => 'boolean',
            'api_enabled' => 'boolean',
        ];
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'updated_by_admin_id');
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate([], [
            'commission_enforcement_mode' => self::MODE_WARNING,
            'block_after_overdue_days' => 0,
            'allow_exit_when_blocked' => true,
            'require_active_client' => true,
            'enforce_floor_match' => false,
            'api_enabled' => true,
        ]);
    }
}
