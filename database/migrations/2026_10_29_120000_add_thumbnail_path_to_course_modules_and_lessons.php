<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_modules', function (Blueprint $table): void {
            $table->string('thumbnail_path', 500)
                ->nullable()
                ->after('description');
        });

        Schema::table('lessons', function (Blueprint $table): void {
            $table->string('thumbnail_path', 500)
                ->nullable()
                ->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropColumn('thumbnail_path');
        });

        Schema::table('course_modules', function (Blueprint $table): void {
            $table->dropColumn('thumbnail_path');
        });
    }
};
