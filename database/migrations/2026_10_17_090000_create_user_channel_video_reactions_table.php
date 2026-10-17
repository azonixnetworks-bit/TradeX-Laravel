<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_channel_video_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_video_id')
                ->constrained('channel_videos')
                ->cascadeOnDelete();
            $table->string('reaction', 10);
            $table->timestamps();

            $table->unique(
                ['user_id', 'channel_video_id'],
                'user_channel_video_reaction_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_channel_video_reactions');
    }
};
