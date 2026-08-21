<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Run The Migration
    |--------------------------------------------------------------------------
    */

    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table): void {

            /*
            |--------------------------------------------------------------------------
            | Primary Identity
            |--------------------------------------------------------------------------
            */

            $table->id();

            $table->uuid('uuid')
                ->unique();

            /*
            |--------------------------------------------------------------------------
            | Parent Module
            |--------------------------------------------------------------------------
            */

            $table->foreignId('course_module_id')
                ->constrained('course_modules')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Business Identity
            |--------------------------------------------------------------------------
            |
            | Lesson code and slug are unique only inside their parent module.
            | Different modules may therefore reuse readable identifiers such
            | as "LESSON-01" or "introduction".
            |
            */

            $table->string('code', 80);

            $table->string('slug', 220);

            /*
            |--------------------------------------------------------------------------
            | Lesson Content
            |--------------------------------------------------------------------------
            */

            $table->string('title', 200);

            $table->string(
                'heading',
                250
            )->nullable();

            $table->string(
                'short_description',
                500
            )->nullable();

            $table->longText(
                'description'
            )->nullable();

            /*
            |--------------------------------------------------------------------------
            | Lesson Availability
            |--------------------------------------------------------------------------
            |
            | Preview lessons may be exposed without the normal premium course
            | entitlement. Authorization remains an application-layer concern.
            |
            */

            $table->boolean(
                'is_preview'
            )->default(false);

            /*
            |--------------------------------------------------------------------------
            | Publication State
            |--------------------------------------------------------------------------
            |
            | Expected values:
            | - DRAFT
            | - PUBLISHED
            | - ARCHIVED
            |
            | String storage is intentional so future workflow changes do not
            | require a MySQL ENUM migration.
            |
            */

            $table->string(
                'status',
                20
            )->default('DRAFT');

            $table->timestamp(
                'published_at'
            )->nullable();

            /*
            |--------------------------------------------------------------------------
            | Display Ordering
            |--------------------------------------------------------------------------
            */

            $table->unsignedInteger(
                'sort_order'
            )->default(0);

            /*
            |--------------------------------------------------------------------------
            | Administrative Attribution
            |--------------------------------------------------------------------------
            */

            $table->foreignId(
                'created_by_admin_id'
            )->nullable()
                ->constrained('admin_users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId(
                'updated_by_admin_id'
            )->nullable()
                ->constrained('admin_users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            /*
            |--------------------------------------------------------------------------
            | Lifecycle Columns
            |--------------------------------------------------------------------------
            */

            $table->timestamps();

            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | Business Constraints
            |--------------------------------------------------------------------------
            */

            $table->unique(
                [
                    'course_module_id',
                    'code',
                ],
                'lessons_module_code_unique'
            );

            $table->unique(
                [
                    'course_module_id',
                    'slug',
                ],
                'lessons_module_slug_unique'
            );

            /*
            |--------------------------------------------------------------------------
            | Query Performance Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(
                [
                    'course_module_id',
                    'status',
                    'sort_order',
                ],
                'lessons_module_status_sort_idx'
            );

            $table->index(
                [
                    'course_module_id',
                    'sort_order',
                ],
                'lessons_module_sort_idx'
            );

            $table->index(
                [
                    'course_module_id',
                    'is_preview',
                    'status',
                ],
                'lessons_module_preview_status_idx'
            );

            $table->index(
                [
                    'status',
                    'published_at',
                ],
                'lessons_status_published_idx'
            );

            $table->index(
                'published_at',
                'lessons_published_at_idx'
            );
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Reverse The Migration
    |--------------------------------------------------------------------------
    */

    public function down(): void
    {
        Schema::dropIfExists(
            'lessons'
        );
    }
};
