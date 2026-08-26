<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'education_content_localizations',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->string('content_type', 30);
                $table->unsignedBigInteger('content_id');
                $table->string('language_code', 10);

                $table->string('title', 255)->nullable();
                $table->string('heading', 255)->nullable();
                $table->text('short_description')->nullable();
                $table->longText('description')->nullable();
                $table->text('submission_instructions')->nullable();

                $table->char('source_sha256', 64);
                $table->char('translation_sha256', 64);
                $table->string('status', 20)->default('DRAFT');
                $table->unsignedInteger('version')->default(1);

                $table->foreignId('updated_by_admin_id')
                    ->nullable()
                    ->constrained('admin_users')
                    ->nullOnDelete();

                $table->foreignId('reviewed_by_admin_id')
                    ->nullable()
                    ->constrained('admin_users')
                    ->nullOnDelete();

                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->foreign('language_code')
                    ->references('code')
                    ->on('language_profiles')
                    ->restrictOnDelete();

                $table->unique(
                    ['content_type', 'content_id', 'language_code'],
                    'edu_content_loc_target_language_uq'
                );

                $table->index(
                    ['language_code', 'status'],
                    'edu_content_loc_language_status_idx'
                );

                $table->index(
                    ['content_type', 'content_id', 'status'],
                    'edu_content_loc_target_status_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'education_content_localizations'
        );
    }
};
