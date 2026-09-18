<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_device_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_device_id')->constrained()->cascadeOnDelete();
            $table->char('session_hash', 64)->unique();
            $table->unsignedInteger('session_version');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('last_seen_at');
            $table->timestamp('expires_at')->index();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_device_sessions');
    }
};
