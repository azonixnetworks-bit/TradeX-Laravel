<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trade_ideas', function (Blueprint $table): void {
            $table->string('after_chart_image_disk')->nullable()->after('chart_image_size_bytes');
            $table->string('after_chart_image_path')->nullable()->after('after_chart_image_disk');
            $table->string('after_chart_image_mime_type', 100)->nullable()->after('after_chart_image_path');
            $table->unsignedBigInteger('after_chart_image_size_bytes')->nullable()->after('after_chart_image_mime_type');
            $table->boolean('show_on_website')->default(false)->after('close_note');
            $table->index(['show_on_website', 'status', 'closed_at'], 'trade_ideas_public_results_index');
        });
    }

    public function down(): void
    {
        Schema::table('trade_ideas', function (Blueprint $table): void {
            $table->dropIndex('trade_ideas_public_results_index');
            $table->dropColumn([
                'after_chart_image_disk',
                'after_chart_image_path',
                'after_chart_image_mime_type',
                'after_chart_image_size_bytes',
                'show_on_website',
            ]);
        });
    }
};
