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
        Schema::create('course_modules', function (Blueprint $table): void {

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
            | Parent Course
            |--------------------------------------------------------------------------
            */

            $table->foreignId('course_id')
                ->constrained('courses')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Business Identity
            |--------------------------------------------------------------------------
            |
            | A module code and slug only need to be unique inside its course.
            | This allows separate courses to reuse clean names such as
            | "MODULE-01" or "introduction" without a global collision.
            |
            */

            $table->string('code', 80);

            $table->string('slug', 220);

            /*
            |--------------------------------------------------------------------------
            | Module Content
            |--------------------------------------------------------------------------
            */

            $table->string('title', 200);

            $table->string(
                'short_description',
                500
            )->nullable();

            $table->longText(
                'description'
            )->nullable();

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
            | String storage is intentional so application rules can evolve
            | without requiring a MySQL ENUM schema change.
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
            |
            | Admin references are nullable so historical education records
            | remain valid even if the originating admin account is removed.
            |
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
                    'course_id',
                    'code',
                ],
                'course_modules_course_code_unique'
            );

            $table->unique(
                [
                    'course_id',
                    'slug',
                ],
                'course_modules_course_slug_unique'
            );

            /*
            |--------------------------------------------------------------------------
            | Query Performance Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(
                [
                    'course_id',
                    'status',
                    'sort_order',
                ],
                'course_modules_course_status_sort_idx'
            );

            $table->index(
                [
                    'course_id',
                    'sort_order',
                ],
                'course_modules_course_sort_idx'
            );

            $table->index(
                [
                    'status',
                    'published_at',
                ],
                'course_modules_status_published_idx'
            );

            $table->index(
                'published_at',
                'course_modules_published_at_idx'
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
            'course_modules'
        );
    }
};
