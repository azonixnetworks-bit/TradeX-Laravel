<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Run The Migration
    |--------------------------------------------------------------------------
    */

    public function up(): void
    {
        Schema::create('lesson_videos', function (Blueprint $table): void {

            /*
            |--------------------------------------------------------------------------
            | Primary Identity
            |--------------------------------------------------------------------------
            */

            $table->id();

            $table->uuid('uuid')
                ->unique();

            /*
            |--------------------------------------------------------------------------
            | Parent Lesson
            |--------------------------------------------------------------------------
            */

            $table->foreignId('lesson_id')
                ->constrained('lessons')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Video Content
            |--------------------------------------------------------------------------
            */

            $table->string('title', 200);

            $table->string(
                'short_description',
                500
            )->nullable();

            $table->longText(
                'description'
            )->nullable();

            /*
            |--------------------------------------------------------------------------
            | Protected Source Storage
            |--------------------------------------------------------------------------
            |
            | Store only logical disk/path information. Never persist a signed
            | playback URL because signed URLs are short-lived and should be
            | generated at request time after entitlement checks.
            |
            | Expected disk examples:
            | - private
            | - s3-private
            |
            */

            $table->string(
                'storage_disk',
                50
            )->default('private');

            $table->string(
                'source_path',
                500
            );

            /*
            |--------------------------------------------------------------------------
            | Adaptive Streaming Output
            |--------------------------------------------------------------------------
            |
            | hls_master_path points to the protected HLS master manifest after
            | transcoding. streaming_asset_key can reference a future external
            | media/CDN asset without exposing a public playback URL.
            |
            */

            $table->string(
                'hls_master_path',
                500
            )->nullable();

            $table->string(
                'streaming_asset_key',
                255
            )->nullable();

            $table->string(
                'thumbnail_path',
                500
            )->nullable();

            /*
            |--------------------------------------------------------------------------
            | Media Metadata
            |--------------------------------------------------------------------------
            */

            $table->unsignedInteger(
                'duration_seconds'
            )->nullable();

            $table->unsignedBigInteger(
                'file_size_bytes'
            )->nullable();

            $table->string(
                'mime_type',
                120
            )->nullable();

            /*
            |--------------------------------------------------------------------------
            | Processing State
            |--------------------------------------------------------------------------
            |
            | Expected values:
            | - PENDING
            | - PROCESSING
            | - READY
            | - FAILED
            |
            | Processing state is intentionally separate from publication
            | state. A video can finish transcoding while still remaining a
            | draft until an authorized education admin publishes it.
            |
            */

            $table->string(
                'processing_status',
                20
            )->default('PENDING');

            $table->text(
                'processing_error'
            )->nullable();

            $table->timestamp(
                'processed_at'
            )->nullable();

            /*
            |--------------------------------------------------------------------------
            | Publication State
            |--------------------------------------------------------------------------
            |
            | Expected values:
            | - DRAFT
            | - PUBLISHED
            | - ARCHIVED
            |
            */

            $table->string(
                'status',
                20
            )->default('DRAFT');

            $table->timestamp(
                'published_at'
            )->nullable();

            /*
            |--------------------------------------------------------------------------
            | Content Protection
            |--------------------------------------------------------------------------
            |
            | Premium lesson videos are not downloadable by default. This flag
            | is an application policy input only; secure delivery still relies
            | on protected storage, authorization and short-lived playback
            | tokens/URLs.
            |
            */

            $table->boolean(
                'is_downloadable'
            )->default(false);

            /*
            |--------------------------------------------------------------------------
            | Display Ordering
            |--------------------------------------------------------------------------
            */

            $table->unsignedInteger(
                'sort_order'
            )->default(0);

            /*
            |--------------------------------------------------------------------------
            | Administrative Attribution
            |--------------------------------------------------------------------------
            */

            $table->foreignId(
                'created_by_admin_id'
            )->nullable()
                ->constrained('admin_users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId(
                'updated_by_admin_id'
            )->nullable()
                ->constrained('admin_users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            /*
            |--------------------------------------------------------------------------
            | Lifecycle Columns
            |--------------------------------------------------------------------------
            */

            $table->timestamps();

            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | Query Performance Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(
                [
                    'lesson_id',
                    'status',
                    'sort_order',
                ],
                'lesson_videos_lesson_status_sort_idx'
            );

            $table->index(
                [
                    'lesson_id',
                    'processing_status',
                ],
                'lesson_videos_lesson_processing_idx'
            );

            $table->index(
                [
                    'processing_status',
                    'processed_at',
                ],
                'lesson_videos_processing_processed_idx'
            );

            $table->index(
                [
                    'status',
                    'published_at',
                ],
                'lesson_videos_status_published_idx'
            );

            $table->index(
                'published_at',
                'lesson_videos_published_at_idx'
            );

            $table->index(
                'streaming_asset_key',
                'lesson_videos_streaming_asset_idx'
            );
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Reverse The Migration
    |--------------------------------------------------------------------------
    */

    public function down(): void
    {
        Schema::dropIfExists(
            'lesson_videos'
        );
    }
};
