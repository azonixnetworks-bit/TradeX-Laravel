<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->string('profile_photo_path', 500)->nullable()->after('telegram_username');
            $table->string('profile_photo_mime_type', 50)->nullable()->after('profile_photo_path');
            $table->unsignedBigInteger('profile_photo_size_bytes')->nullable()->after('profile_photo_mime_type');
            $table->char('profile_photo_sha256', 64)->nullable()->after('profile_photo_size_bytes');
            $table->unsignedBigInteger('profile_photo_version')->default(0)->after('profile_photo_sha256');
            $table->timestamp('profile_photo_updated_at')->nullable()->after('profile_photo_version');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'profile_photo_path',
                'profile_photo_mime_type',
                'profile_photo_size_bytes',
                'profile_photo_sha256',
                'profile_photo_version',
                'profile_photo_updated_at',
            ]);
        });
    }
};
