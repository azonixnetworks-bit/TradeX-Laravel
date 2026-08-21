<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isLegacyAdmin05Schema =
            Schema::hasColumn(
                'trade_ideas',
                'language_code'
            )
            || Schema::hasColumn(
                'trade_ideas',
                'valid_until'
            );

        /*
        |--------------------------------------------------------------------------
        | Upgrade an already-migrated REV1 / REV2 database
        |--------------------------------------------------------------------------
        |
        | A fresh REV3 install already creates these columns as nullable in the
        | original create_trade_ideas migration, so no ALTER is needed there.
        |
        */

        if ($isLegacyAdmin05Schema) {
            Schema::table('trade_ideas', function (Blueprint $table): void {
                $table->decimal(
                    'entry_price_low',
                    24,
                    10
                )->nullable()
                    ->change();

                $table->decimal(
                    'entry_price_high',
                    24,
                    10
                )->nullable()
                    ->change();

                $table->decimal(
                    'stop_loss',
                    24,
                    10
                )->nullable()
                    ->change();
            });
        }

        if (
            Schema::hasColumn(
                'trade_ideas',
                'language_code'
            )
        ) {
            Schema::table('trade_ideas', function (Blueprint $table): void {
                $table->dropColumn(
                    'language_code'
                );
            });
        }

        if (
            Schema::hasColumn(
                'trade_ideas',
                'valid_until'
            )
        ) {
            Schema::table('trade_ideas', function (Blueprint $table): void {
                $table->dropColumn(
                    'valid_until'
                );
            });
        }
    }

    public function down(): void
    {
        if (
            ! Schema::hasColumn(
                'trade_ideas',
                'language_code'
            )
        ) {
            Schema::table('trade_ideas', function (Blueprint $table): void {
                $table->string(
                    'language_code',
                    10
                )->default('en')
                    ->after('timeframe');
            });
        }

        if (
            ! Schema::hasColumn(
                'trade_ideas',
                'valid_until'
            )
        ) {
            Schema::table('trade_ideas', function (Blueprint $table): void {
                $table->timestamp(
                    'valid_until'
                )->nullable()
                    ->after('entry_price_actual');
            });
        }

        // Structure remains nullable on rollback because REV3 may already
        // contain records that intentionally have no Trade Structure.
    }
};
