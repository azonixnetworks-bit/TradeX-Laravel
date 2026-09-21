<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('platform', 32)->unique();
            $table->boolean('is_enabled')->default(false);
            $table->longText('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();
            $table->string('last_test_status', 24)->default('UNTESTED');
            $table->text('last_test_message')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->unsignedBigInteger('version')->default(0);

            $table->foreignId('updated_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index([
                'is_enabled',
                'platform',
            ]);

            $table->index([
                'last_test_status',
                'platform',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_connections');
    }
};
