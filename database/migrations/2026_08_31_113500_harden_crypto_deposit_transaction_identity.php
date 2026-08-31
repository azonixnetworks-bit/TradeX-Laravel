<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE =
        'crypto_deposits';

    private const OLD_TX_UNIQUE =
        'crypto_deposits_tx_hash_unique';

    private const DIGEST_UNIQUE =
        'crypto_deposits_tx_hash_digest_unique';

    public function up(): void
    {
        /*
         * Existing ADMIN-23B2 installations may already have executed the
         * original 113400 migration. Editing that old migration later does
         * not upgrade an already-running MySQL database, so this forward
         * migration performs the compatibility upgrade.
         */
        if (
            ! Schema::hasColumn(
                self::TABLE,
                'tx_hash_digest'
            )
        ) {
            Schema::table(
                self::TABLE,
                function (
                    Blueprint $table
                ): void {
                    $table->char(
                        'tx_hash_digest',
                        64
                    )
                        ->nullable()
                        ->after(
                            'tx_hash'
                        );
                }
            );
        }

        /*
         * Backfill any pre-hardening evidence using exactly the same
         * canonical network-scoped identity as the application model/service.
         */
        DB::table(
            self::TABLE
        )
            ->select([
                'id',
                'network',
                'tx_hash',
                'tx_hash_digest',
            ])
            ->whereNull(
                'tx_hash_digest'
            )
            ->orderBy(
                'id'
            )
            ->chunkById(
                200,
                function (
                    $rows
                ): void {
                    foreach (
                        $rows
                        as $row
                    ) {
                        $network =
                            strtoupper(
                                trim(
                                    (string) $row
                                        ->network
                                )
                            );

                        $txHash =
                            trim(
                                (string) $row
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

                        DB::table(
                            self::TABLE
                        )
                            ->where(
                                'id',
                                $row->id
                            )
                            ->update([
                                'tx_hash_digest' =>
                                    hash(
                                        'sha256',
                                        $network
                                        ."\0"
                                        .$identity
                                    ),
                            ]);
                    }
                }
            );

        /*
         * ADMIN-23B2 originally made textual tx_hash globally unique.
         * ADMIN-23C scopes duplicate identity to network instead.
         */
        if (
            Schema::hasIndex(
                self::TABLE,
                self::OLD_TX_UNIQUE
            )
        ) {
            Schema::table(
                self::TABLE,
                function (
                    Blueprint $table
                ): void {
                    $table->dropUnique(
                        self::OLD_TX_UNIQUE
                    );
                }
            );
        }

        if (
            ! Schema::hasIndex(
                self::TABLE,
                self::DIGEST_UNIQUE
            )
        ) {
            Schema::table(
                self::TABLE,
                function (
                    Blueprint $table
                ): void {
                    $table->unique(
                        'tx_hash_digest',
                        self::DIGEST_UNIQUE
                    );
                }
            );
        }

        /*
         * Fresh ADMIN-23C databases already created this column NOT NULL.
         * Older upgraded MySQL databases receive the same invariant after
         * backfill. SQLite test databases normally skip this branch because
         * fresh 113400 already owns the hardened definition.
         */
        if (
            DB::connection()
                ->getDriverName()
            === 'mysql'
        ) {
            Schema::table(
                self::TABLE,
                function (
                    Blueprint $table
                ): void {
                    $table->char(
                        'tx_hash_digest',
                        64
                    )
                        ->nullable(
                            false
                        )
                        ->change();
                }
            );
        }
    }

    public function down(): void
    {
        /*
         * Do not restore the legacy globally-unique tx_hash constraint:
         * two different networks may now legitimately share the same textual
         * transaction identifier.
         */
        if (
            Schema::hasIndex(
                self::TABLE,
                self::DIGEST_UNIQUE
            )
        ) {
            Schema::table(
                self::TABLE,
                function (
                    Blueprint $table
                ): void {
                    $table->dropUnique(
                        self::DIGEST_UNIQUE
                    );
                }
            );
        }

        if (
            Schema::hasColumn(
                self::TABLE,
                'tx_hash_digest'
            )
        ) {
            Schema::table(
                self::TABLE,
                function (
                    Blueprint $table
                ): void {
                    $table->dropColumn(
                        'tx_hash_digest'
                    );
                }
            );
        }
    }
};
