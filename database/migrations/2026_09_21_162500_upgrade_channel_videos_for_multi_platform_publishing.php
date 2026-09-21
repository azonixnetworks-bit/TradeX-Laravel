<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_videos', function (Blueprint $table): void {
            $table->char('youtube_video_id', 11)
                ->nullable()
                ->change();

            $table->string('youtube_url', 255)
                ->nullable()
                ->change();

            $table->string('source_disk', 32)
                ->nullable()
                ->after('youtube_url');

            $table->string('source_path', 1024)
                ->nullable()
                ->after('source_disk');

            $table->string('source_original_name', 255)
                ->nullable()
                ->after('source_path');

            $table->string('source_mime_type', 120)
                ->nullable()
                ->after('source_original_name');

            $table->unsignedBigInteger('source_size_bytes')
                ->nullable()
                ->after('source_mime_type');

            $table->char('source_sha256', 64)
                ->nullable()
                ->after('source_size_bytes');

            $table->text('hashtags')
                ->nullable()
                ->after('description');

            $table->timestamp('publish_requested_at')
                ->nullable()
                ->after('published_at');

            $table->index('publish_requested_at');
        });
    }

    public function down(): void
    {
        $hasUploadOnlyRows = DB::table('channel_videos')
            ->whereNull('youtube_video_id')
            ->orWhereNull('youtube_url')
            ->exists();

        if ($hasUploadOnlyRows) {
            throw new \RuntimeException(
                'Cannot rollback multi-platform channel media while upload-only channel videos exist.'
            );
        }

        Schema::table('channel_videos', function (Blueprint $table): void {
            $table->dropIndex(['publish_requested_at']);

            $table->dropColumn([
                'source_disk',
                'source_path',
                'source_original_name',
                'source_mime_type',
                'source_size_bytes',
                'source_sha256',
                'hashtags',
                'publish_requested_at',
            ]);

            $table->char('youtube_video_id', 11)
                ->nullable(false)
                ->change();

            $table->string('youtube_url', 255)
                ->nullable(false)
                ->change();
        });
    }
};
