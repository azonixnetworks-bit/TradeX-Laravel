<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trade_ideas', function (Blueprint $table): void {
            $table->string(
                'tradingview_url',
                2048
            )->nullable()
                ->after('analysis');

            $table->string(
                'chart_image_disk',
                32
            )->nullable()
                ->after('tradingview_url');

            $table->string(
                'chart_image_path',
                1024
            )->nullable()
                ->after('chart_image_disk');

            $table->string(
                'chart_image_mime_type',
                100
            )->nullable()
                ->after('chart_image_path');

            $table->unsignedBigInteger(
                'chart_image_size_bytes'
            )->nullable()
                ->after('chart_image_mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('trade_ideas', function (Blueprint $table): void {
            $table->dropColumn([
                'tradingview_url',
                'chart_image_disk',
                'chart_image_path',
                'chart_image_mime_type',
                'chart_image_size_bytes',
            ]);
        });
    }
};
