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
        Schema::create('assignments', function (Blueprint $table): void {

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
            | Parent Lesson
            |--------------------------------------------------------------------------
            */

            $table->foreignId('lesson_id')
                ->constrained('lessons')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Assignment Content
            |--------------------------------------------------------------------------
            */

            $table->string('title', 200);

            $table->string(
                'heading',
                250
            )->nullable();

            $table->longText(
                'description'
            )->nullable();

            /*
            |--------------------------------------------------------------------------
            | Assignment Reference Media
            |--------------------------------------------------------------------------
            |
            | This is only for assignment instructions/reference artwork.
            | It is NOT a learner submission upload field.
            |
            */

            $table->string(
                'reference_image_path',
                500
            )->nullable();

            /*
            |--------------------------------------------------------------------------
            | External Submission Policy
            |--------------------------------------------------------------------------
            |
            | TradeX does not store the learner's assignment file in this
            | assignment record. The learner submits externally, currently
            | through WhatsApp.
            |
            | Expected values:
            | - WHATSAPP
            | - NONE
            |
            | Future external channels may be added without changing a MySQL
            | ENUM definition.
            |
            */

            $table->string(
                'submission_method',
                30
            )->default('WHATSAPP');

            $table->text(
                'submission_instructions'
            )->nullable();

            $table->boolean(
                'is_required'
            )->default(true);

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
            | Query Performance Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(
                [
                    'lesson_id',
                    'status',
                    'sort_order',
                ],
                'assignments_lesson_status_sort_idx'
            );

            $table->index(
                [
                    'lesson_id',
                    'submission_method',
                    'status',
                ],
                'assignments_lesson_submission_status_idx'
            );

            $table->index(
                [
                    'lesson_id',
                    'is_required',
                ],
                'assignments_lesson_required_idx'
            );

            $table->index(
                [
                    'status',
                    'published_at',
                ],
                'assignments_status_published_idx'
            );

            $table->index(
                'published_at',
                'assignments_published_at_idx'
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
            'assignments'
        );
    }
};
