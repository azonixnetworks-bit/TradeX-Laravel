<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class FundedNoTradeRule extends Model
{
    use SoftDeletes;

    public const POLICY_RESTRICTED = 'RESTRICTED';
    public const POLICY_ALLOWED = 'ALLOWED';
    public const POLICY_CONDITIONAL = 'CONDITIONAL';

    public const PHASE_ALL = 'ALL';
    public const PHASE_CHALLENGE = 'CHALLENGE';
    public const PHASE_VERIFICATION = 'VERIFICATION';
    public const PHASE_FUNDED = 'FUNDED';

    protected $fillable = [
        'uuid',
        'funded_account_partner_id',
        'program_name',
        'phase',
        'policy',
        'impact_levels',
        'minutes_before',
        'minutes_after',
        'affected_instruments_only',
        'restricted_currencies',
        'restricted_instruments',
        'block_open_new_trades',
        'block_close_trades',
        'block_pending_orders',
        'rule_summary',
        'source_url',
        'source_reference',
        'verified_at',
        'effective_from',
        'expires_at',
        'is_active',
        'priority',
        'created_by_admin_id',
        'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'impact_levels' => 'array',
            'minutes_before' => 'integer',
            'minutes_after' => 'integer',
            'affected_instruments_only' => 'boolean',
            'restricted_currencies' => 'array',
            'restricted_instruments' => 'array',
            'block_open_new_trades' => 'boolean',
            'block_close_trades' => 'boolean',
            'block_pending_orders' => 'boolean',
            'verified_at' => 'datetime',
            'effective_from' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $rule): void {
            if (blank($rule->uuid)) {
                $rule->uuid = (string) Str::uuid();
            }

            self::normalize($rule);
        });

        static::updating(function (self $rule): void {
            self::normalize($rule);
        });
    }

    private static function normalize(self $rule): void
    {
        foreach (['phase', 'policy'] as $field) {
            if ($rule->{$field} !== null) {
                $rule->{$field} = strtoupper(trim((string) $rule->{$field}));
            }
        }

        $rule->program_name = filled($rule->program_name)
            ? trim((string) $rule->program_name)
            : null;

        $rule->impact_levels = self::normalizeList($rule->impact_levels, true);
        $rule->restricted_currencies = self::normalizeList($rule->restricted_currencies, true);
        $rule->restricted_instruments = self::normalizeList($rule->restricted_instruments, true);
    }

    /** @return array<int, string>|null */
    private static function normalizeList(mixed $value, bool $uppercase = false): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $items = array_values(array_unique(array_filter(array_map(
            static function (mixed $item) use ($uppercase): string {
                $item = trim((string) $item);
                return $uppercase ? strtoupper($item) : $item;
            },
            $value
        ), static fn (string $item): bool => $item !== '')));

        return $items === [] ? null : $items;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(FundedAccountPartner::class, 'funded_account_partner_id');
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by_admin_id');
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'updated_by_admin_id');
    }

    public function isEffectiveAt(CarbonInterface $at): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->effective_from !== null && $at->lt($this->effective_from)) {
            return false;
        }

        if ($this->expires_at !== null && ! $at->lt($this->expires_at)) {
            return false;
        }

        return true;
    }

    public function appliesToImpact(string $impact): bool
    {
        $levels = array_map('strtoupper', (array) $this->impact_levels);

        return $levels === [] || in_array(strtoupper($impact), $levels, true);
    }
}
