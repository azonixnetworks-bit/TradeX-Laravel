<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_videos', function (Blueprint $table): void {
            $table->id();

            $table->uuid('uuid')
                ->unique();

            $table->string(
                'title',
                180
            );

            $table->string(
                'short_description',
                500
            )->nullable();

            $table->text(
                'description'
            )->nullable();

            $table->char(
                'youtube_video_id',
                11
            )->unique();

            $table->string(
                'youtube_url',
                255
            );

            $table->string(
                'language_code',
                10
            )->default('en');

            $table->string(
                'status',
                16
            )->default('DRAFT');

            $table->timestamp(
                'published_at'
            )->nullable();

            $table->boolean(
                'is_featured'
            )->default(false);

            $table->unsignedInteger(
                'sort_order'
            )->default(0);

            $table->foreignId(
                'created_by_admin_id'
            )
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->foreignId(
                'updated_by_admin_id'
            )
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index([
                'status',
                'sort_order',
            ]);

            $table->index([
                'language_code',
                'status',
            ]);

            $table->index([
                'is_featured',
                'status',
            ]);

            $table->index(
                'published_at'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'channel_videos'
        );
    }
};
