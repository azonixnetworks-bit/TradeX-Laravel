<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trading_floor_reminders', function (Blueprint $table): void {
            $table->unsignedBigInteger('trading_floor_membership_id')
                ->nullable()
                ->after('trading_floor_client_id');

            $table->index(
                ['trading_floor_membership_id', 'reminder_type'],
                'tf_reminder_membership_type_idx'
            );

            $table->foreign(
                'trading_floor_membership_id',
                'tf_reminder_membership_fk'
            )
                ->references('id')
                ->on('trading_floor_memberships')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trading_floor_reminders', function (Blueprint $table): void {
            $table->dropForeign('tf_reminder_membership_fk');
            $table->dropIndex('tf_reminder_membership_type_idx');
            $table->dropColumn('trading_floor_membership_id');
        });
    }
};
