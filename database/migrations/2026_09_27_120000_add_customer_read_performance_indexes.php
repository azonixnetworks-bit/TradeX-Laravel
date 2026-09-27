<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('economic_events', function (Blueprint $table): void {
            $table->index(
                ['is_published', 'currency', 'scheduled_at_utc'],
                'economic_events_pub_currency_schedule_idx'
            );

            $table->index(
                ['is_published', 'status', 'scheduled_at_utc'],
                'economic_events_pub_status_schedule_idx'
            );
        });

        Schema::table('economic_event_pairs', function (Blueprint $table): void {
            $table->index(
                ['symbol', 'economic_event_id'],
                'economic_event_pairs_symbol_event_idx'
            );
        });

        Schema::table('economic_event_updates', function (Blueprint $table): void {
            $table->index(
                ['is_published', 'economic_event_id', 'published_at'],
                'economic_event_updates_pub_event_time_idx'
            );
        });

        Schema::table('trade_ideas', function (Blueprint $table): void {
            $table->index(
                ['status', 'published_at', 'id'],
                'trade_ideas_status_publish_id_idx'
            );

            $table->index(
                ['symbol', 'status', 'published_at'],
                'trade_ideas_symbol_status_publish_idx'
            );
        });

        Schema::table('channel_videos', function (Blueprint $table): void {
            $table->index(
                ['status', 'sort_order', 'published_at', 'id'],
                'channel_videos_status_sort_publish_idx'
            );

            $table->index(
                ['language_code', 'status', 'sort_order', 'published_at'],
                'channel_videos_lang_status_sort_publish_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('channel_videos', function (Blueprint $table): void {
            $table->dropIndex('channel_videos_lang_status_sort_publish_idx');
            $table->dropIndex('channel_videos_status_sort_publish_idx');
        });

        Schema::table('trade_ideas', function (Blueprint $table): void {
            $table->dropIndex('trade_ideas_symbol_status_publish_idx');
            $table->dropIndex('trade_ideas_status_publish_id_idx');
        });

        Schema::table('economic_event_updates', function (Blueprint $table): void {
            $table->dropIndex('economic_event_updates_pub_event_time_idx');
        });

        Schema::table('economic_event_pairs', function (Blueprint $table): void {
            $table->dropIndex('economic_event_pairs_symbol_event_idx');
        });

        Schema::table('economic_events', function (Blueprint $table): void {
            $table->dropIndex('economic_events_pub_status_schedule_idx');
            $table->dropIndex('economic_events_pub_currency_schedule_idx');
        });
    }
};
