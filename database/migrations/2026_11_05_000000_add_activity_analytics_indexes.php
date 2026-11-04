<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_activity_events', function (Blueprint $table): void {
            $table->index('occurred_at', 'activity_occurred_at_idx');
            $table->index(
                ['user_id', 'session_id', 'occurred_at'],
                'activity_session_timeline_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('user_activity_events', function (Blueprint $table): void {
            $table->dropIndex('activity_occurred_at_idx');
            $table->dropIndex('activity_session_timeline_idx');
        });
    }
};
