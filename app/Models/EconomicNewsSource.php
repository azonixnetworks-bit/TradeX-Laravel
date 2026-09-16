<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EconomicNewsSource extends Model
{
    public const TYPE_MANUAL = 'MANUAL';

    public const TYPE_API = 'API';

    public const TYPE_RSS = 'RSS';

    public const TYPE_WEBHOOK = 'WEBHOOK';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'source_type',
        'homepage_url',
        'is_enabled',
        'priority',
        'reliability_weight',
        'public_config_json',
        'notes',
        'last_fetched_at',
        'last_success_at',
        'last_error_at',
        'last_error_code',
        'last_error_message',
        'created_by_admin_id',
        'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'priority' => 'integer',
            'reliability_weight' => 'decimal:2',
            'public_config_json' => 'array',
            'last_fetched_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $source): void {
            if (blank($source->uuid)) {
                $source->uuid = (string) Str::uuid();
            }

            $source->code = strtoupper(trim((string) $source->code));
            $source->source_type = strtoupper(trim((string) $source->source_type));
        });

        static::updating(function (self $source): void {
            if ($source->isDirty('code')) {
                $source->code = strtoupper(trim((string) $source->code));
            }

            if ($source->isDirty('source_type')) {
                $source->source_type = strtoupper(trim((string) $source->source_type));
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function events(): HasMany
    {
        return $this->hasMany(EconomicEvent::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(EconomicEventUpdate::class);
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by_admin_id');
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'updated_by_admin_id');
    }
}
