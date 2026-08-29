<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class CopyTradeBillingCalendarDay extends Model
{
    protected $fillable = [
        'uuid',
        'market_category',
        'calendar_date',
        'is_billable_day',
        'source',
        'source_reference',
        'created_by_admin_id',
        'locked_at',
    ];

    protected $hidden = [
        'source_reference',
    ];

    protected function casts(): array
    {
        return [
            'calendar_date' => 'date',
            'is_billable_day' => 'boolean',
            'source_reference' => 'encrypted',
            'locked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $day): void {
            if (blank($day->uuid)) {
                $day->uuid = (string) Str::uuid();
            }
        });

        static::updating(function (self $day): void {
            if ($day->getOriginal('locked_at') !== null) {
                throw new LogicException(
                    'Locked Copy Trade billing calendar days are immutable.'
                );
            }
        });

        static::deleting(function (self $day): void {
            if ($day->locked_at !== null) {
                throw new LogicException(
                    'Locked Copy Trade billing calendar days cannot be deleted.'
                );
            }
        });
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'created_by_admin_id'
        );
    }
}
