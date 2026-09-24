<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_video_publications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('channel_video_id')
                ->constrained('channel_videos')
                ->cascadeOnDelete();

            $table->string('platform', 32);
            $table->string('status', 32)->default('PENDING');
            $table->unsignedSmallInteger('attempt_count')->default(0);

            $table->string('provider_post_id', 255)->nullable();
            $table->text('provider_url')->nullable();
            $table->text('status_message')->nullable();
            $table->json('response_meta')->nullable();

            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->timestamps();

            $table->unique([
                'channel_video_id',
                'platform',
            ], 'channel_video_platform_unique');

            $table->index([
                'platform',
                'status',
            ]);

            $table->index([
                'status',
                'queued_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_video_publications');
    }
};
