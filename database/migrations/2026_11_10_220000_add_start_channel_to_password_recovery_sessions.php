<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('password_recovery_sessions', function (Blueprint $table): void {
            $table->string('start_channel', 10)->default('EMAIL');
        });
    }

    public function down(): void
    {
        Schema::table('password_recovery_sessions', function (Blueprint $table): void {
            $table->dropColumn('start_channel');
        });
    }
};
