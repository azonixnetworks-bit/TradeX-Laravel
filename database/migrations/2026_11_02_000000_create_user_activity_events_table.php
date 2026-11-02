<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_activity_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('event_id');
            $table->string('module', 40);
            $table->string('event_name', 80);
            $table->string('session_id', 80)->nullable();
            $table->string('subject_id', 80)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique(['user_id', 'event_id']);
            $table->index(['user_id', 'occurred_at']);
            $table->index(['module', 'event_name', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_events');
    }
};
