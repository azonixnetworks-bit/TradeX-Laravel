<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64);
            $table->string('device_slot', 16);
            $table->string('status', 16);
            $table->unsignedInteger('session_version')->default(1);
            // NULL for blocked/revoked records; enforces one active owner per slot.
            $table->string('active_slot', 16)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['admin_user_id', 'token_hash']);
            $table->unique(['admin_user_id', 'active_slot']);
        });
        Schema::create('admin_device_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 60);
            $table->string('device_slot', 16);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
        Schema::create('admin_device_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_device_incident_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16);
            $table->string('recipient');
            $table->string('status', 24)->default('PENDING');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('failure_code')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['admin_device_incident_id', 'channel', 'recipient'], 'admin_device_alert_recipient_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_device_alerts');
        Schema::dropIfExists('admin_device_incidents');
        Schema::dropIfExists('admin_devices');
    }
};
