<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CryptoDeposit extends Model
{
    public const STATUS_SUBMITTED =
        'SUBMITTED';
    public const STATUS_APPROVED =
        'APPROVED';
    public const STATUS_REJECTED =
        'REJECTED';

    protected $fillable = [
        'uuid',
        'payment_order_id',
        'user_id',
        'coin_symbol',
        'network',
        'tx_hash',
        'tx_hash_digest',
        'sender_address',
        'amount_decimal',
        'amount_scale',
        'proof_disk',
        'proof_path',
        'status',
        'submitted_at',
        'reviewed_by_admin_id',
        'reviewed_at',
        'review_reason',
    ];

    protected $hidden = [
        'tx_hash_digest',
        'sender_address',
        'proof_disk',
        'proof_path',
        'reviewed_by_admin_id',
        'review_reason',
    ];

    protected function casts(): array
    {
        return [
            'sender_address' =>
                'encrypted',
            'proof_path' =>
                'encrypted',
            'amount_scale' =>
                'integer',
            'submitted_at' =>
                'datetime',
            'reviewed_at' =>
                'datetime',
            'review_reason' =>
                'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::creating(
            function (
                self $deposit
            ): void {
                if (
                    blank(
                        $deposit->uuid
                    )
                ) {
                    $deposit->uuid =
                        (string) Str::uuid();
                }

                /*
                 * Duplicate identity belongs to the immutable blockchain
                 * network contract. Keep the invariant at the model boundary
                 * as well as in PreparedManualPaymentService so direct model
                 * creation by tests/jobs/admin tooling cannot omit it.
                 */
                if (
                    blank(
                        $deposit
                            ->tx_hash_digest
                    )
                    && filled(
                        $deposit
                            ->network
                    )
                    && filled(
                        $deposit
                            ->tx_hash
                    )
                ) {
                    $network =
                        strtoupper(
                            trim(
                                (string) $deposit
                                    ->network
                            )
                        );

                    $txHash =
                        trim(
                            (string) $deposit
                                ->tx_hash
                        );

                    $identity =
                        preg_match(
                            '/^(?:0x)?[A-Fa-f0-9]{32,191}$/',
                            $txHash
                        ) === 1
                            ? strtolower(
                                $txHash
                            )
                            : $txHash;

                    $deposit
                        ->tx_hash_digest =
                        hash(
                            'sha256',
                            $network
                            ."\0"
                            .$identity
                        );
                }
            }
        );
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(
            PaymentOrder::class,
            'payment_order_id'
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function reviewedByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'reviewed_by_admin_id'
        );
    }
}
