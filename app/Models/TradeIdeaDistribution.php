<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TradeIdeaDistribution extends Model
{
    public const EVENT_INITIAL = 'INITIAL';
    public const EVENT_UPDATE = 'UPDATE';
    public const EVENT_CLOSED = 'CLOSED';
    public const EVENT_MANUAL = 'MANUAL';

    public const CHANNEL_TELEGRAM = 'TELEGRAM';
    public const CHANNEL_WHATSAPP = 'WHATSAPP';

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_PROCESSING = 'PROCESSING';
    public const STATUS_SENT = 'SENT';
    public const STATUS_FAILED = 'FAILED';

    protected $fillable = [
        'uuid',
        'trade_idea_id',
        'trade_idea_update_id',
        'event_type',
        'channel',
        'destination',
        'destination_hash',
        'idempotency_key',
        'status',
        'payload_sha256',
        'payload_snapshot',
        'attempt_count',
        'provider_message_id',
        'last_error_code',
        'last_error_message',
        'response_meta',
        'queued_at',
        'started_at',
        'sent_at',
        'failed_at',
        'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'destination' => 'encrypted',
            'payload_snapshot' => 'array',
            'response_meta' => 'array',
            'attempt_count' => 'integer',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (
            TradeIdeaDistribution $distribution
        ): void {
            if (
                $distribution->uuid === null
                || trim(
                    (string) $distribution->uuid
                ) === ''
            ) {
                $distribution->uuid =
                    Str::uuid()->toString();
            }
        });
    }

    public function tradeIdea(): BelongsTo
    {
        return $this->belongsTo(
            TradeIdea::class
        );
    }

    public function tradeIdeaUpdate(): BelongsTo
    {
        return $this->belongsTo(
            TradeIdeaUpdate::class
        );
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'created_by_admin_id'
        );
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function maskedDestination(): string
    {
        $destination = (string) $this->destination;

        if (
            str_starts_with(
                $destination,
                '@'
            )
        ) {
            return $destination;
        }

        $length = mb_strlen(
            $destination
        );

        if ($length <= 6) {
            return str_repeat(
                '•',
                max(
                    1,
                    $length
                )
            );
        }

        return mb_substr(
            $destination,
            0,
            3
        )
            .str_repeat(
                '•',
                max(
                    3,
                    $length - 7
                )
            )
            .mb_substr(
                $destination,
                -4
            );
    }
}
