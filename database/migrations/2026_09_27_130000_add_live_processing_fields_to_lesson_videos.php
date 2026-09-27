<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_videos', function (Blueprint $table): void {
            $table->json('processing_detail_json')
                ->nullable()
                ->after('processing_stage');

            $table->timestamp('processing_started_at')
                ->nullable()
                ->after('processing_detail_json');

            $table->timestamp('processing_heartbeat_at')
                ->nullable()
                ->after('processing_started_at');

            $table->index(
                ['processing_status', 'processing_heartbeat_at'],
                'lesson_videos_processing_heartbeat_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('lesson_videos', function (Blueprint $table): void {
            $table->dropIndex('lesson_videos_processing_heartbeat_idx');
            $table->dropColumn([
                'processing_detail_json',
                'processing_started_at',
                'processing_heartbeat_at',
            ]);
        });
    }
};
