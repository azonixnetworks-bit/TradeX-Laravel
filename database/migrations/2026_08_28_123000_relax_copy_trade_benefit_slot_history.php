<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * ADMIN-23A was applied locally before replacement semantics were
         * frozen into the engine. Fresh installs already receive the
         * non-unique slot-history index from the corrected foundation
         * migration. This migration safely upgrades databases that ran the
         * earlier ADMIN-23A unique index.
         */
        if (
            Schema::hasIndex(
                'copy_trade_account_benefits',
                'copy_trade_benefit_user_market_slot_uq'
            )
        ) {
            Schema::table(
                'copy_trade_account_benefits',
                function (Blueprint $table): void {
                    $table->dropUnique(
                        'copy_trade_benefit_user_market_slot_uq'
                    );
                }
            );
        }

        if (
            ! Schema::hasIndex(
                'copy_trade_account_benefits',
                'copy_trade_benefit_user_market_slot_idx'
            )
        ) {
            Schema::table(
                'copy_trade_account_benefits',
                function (Blueprint $table): void {
                    $table->index(
                        ['user_id', 'market_category', 'account_slot'],
                        'copy_trade_benefit_user_market_slot_idx'
                    );
                }
            );
        }
    }

    public function down(): void
    {
        /*
         * Do not recreate the old unique constraint automatically.
         * Once replacement history exists, restoring that uniqueness would
         * destroy a valid non-resettable benefit audit model.
         */
        if (
            Schema::hasIndex(
                'copy_trade_account_benefits',
                'copy_trade_benefit_user_market_slot_idx'
            )
        ) {
            Schema::table(
                'copy_trade_account_benefits',
                function (Blueprint $table): void {
                    $table->dropIndex(
                        'copy_trade_benefit_user_market_slot_idx'
                    );
                }
            );
        }
    }
};
