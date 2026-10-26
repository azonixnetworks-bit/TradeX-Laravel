<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trade_journal_entries', function (Blueprint $table): void {
            $table->index(
                ['user_id', 'status', 'opened_at'],
                'trade_journal_user_status_opened_idx'
            );

            $table->index(
                ['user_id', 'status', 'trade_at'],
                'trade_journal_user_status_trade_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('trade_journal_entries', function (Blueprint $table): void {
            $table->dropIndex('trade_journal_user_status_opened_idx');
            $table->dropIndex('trade_journal_user_status_trade_idx');
        });
    }
};
