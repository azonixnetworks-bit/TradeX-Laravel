<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_videos', function (Blueprint $table): void {
            $table->unsignedTinyInteger('processing_progress')->default(0)->after('processing_status');
            $table->string('processing_stage', 160)->nullable()->after('processing_progress');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_videos', function (Blueprint $table): void {
            $table->dropColumn(['processing_progress', 'processing_stage']);
        });
    }
};
