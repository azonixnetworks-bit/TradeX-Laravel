<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('second_brain_knowledge_subjects', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 120);
            $table->string('slug', 160)->unique();
            $table->text('description')->nullable();
            $table->string('market_scope', 32)->default('COMMON');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['market_scope', 'name'], 'sbk_subject_market_name_unique');
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('second_brain_knowledge_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('subject_id')
                ->constrained('second_brain_knowledge_subjects')
                ->restrictOnDelete();
            $table->string('heading', 160)->nullable();
            $table->string('title', 200);
            $table->longText('notes')->nullable();
            $table->string('market', 32)->default('COMMON');
            $table->json('timeframes')->nullable();
            $table->string('knowledge_type', 40)->default('NOTE');
            $table->json('tags')->nullable();
            $table->string('status', 32)->default('DRAFT');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('founder_approved_at')->nullable();
            $table->foreignId('approved_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();
            $table->foreignId('created_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();
            $table->foreignId('updated_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'market']);
            $table->index(['subject_id', 'knowledge_type']);
            $table->index(['created_at', 'status']);
        });

        Schema::create('second_brain_knowledge_media', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('knowledge_item_id')
                ->constrained('second_brain_knowledge_items')
                ->cascadeOnDelete();
            $table->string('media_type', 20);
            $table->string('disk', 40)->default('private');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120)->nullable();
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->char('sha256', 64);
            $table->json('metadata')->nullable();
            $table->foreignId('uploaded_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['knowledge_item_id', 'media_type']);
            $table->index('sha256');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('second_brain_knowledge_media');
        Schema::dropIfExists('second_brain_knowledge_items');
        Schema::dropIfExists('second_brain_knowledge_subjects');
    }
};
