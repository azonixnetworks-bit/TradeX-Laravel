<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {

            $table->id();

            $table->uuid('uuid')
                ->unique();

            /*
            |--------------------------------------------------------------------------
            | Stable Course Identity
            |--------------------------------------------------------------------------
            |
            | code = internal business identifier used by application rules,
            | entitlements and integrations.
            |
            | slug = human-readable URL identifier used by admin/user interfaces.
            |
            */

            $table->string('code', 80)
                ->unique();

            $table->string('slug', 220)
                ->unique();

            /*
            |--------------------------------------------------------------------------
            | Course Content
            |--------------------------------------------------------------------------
            */

            $table->string('title', 200);

            $table->string('short_description', 500)
                ->nullable();

            $table->longText('description')
                ->nullable();

            $table->string('thumbnail_path', 500)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Language
            |--------------------------------------------------------------------------
            |
            | This stores the source/default language of the course.
            | Translation management can be added separately without changing
            | the course identity.
            |
            */

            $table->string('language_code', 10);

            /*
            |--------------------------------------------------------------------------
            | Access
            |--------------------------------------------------------------------------
            |
            | Expected values:
            |
            | FREE
            | PREMIUM
            |
            | Kept as a string instead of a database ENUM so future entitlement
            | rules can evolve without requiring an ENUM migration.
            |
            */

            $table->string('access_level', 20)
                ->default('PREMIUM');

            /*
            |--------------------------------------------------------------------------
            | Publishing Lifecycle
            |--------------------------------------------------------------------------
            |
            | Expected values:
            |
            | DRAFT
            | PUBLISHED
            | ARCHIVED
            |
            */

            $table->string('status', 20)
                ->default('DRAFT');

            $table->timestamp('published_at')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Display Controls
            |--------------------------------------------------------------------------
            */

            $table->unsignedInteger('sort_order')
                ->default(0);

            $table->boolean('is_featured')
                ->default(false);

            /*
            |--------------------------------------------------------------------------
            | Admin Ownership / Audit References
            |--------------------------------------------------------------------------
            |
            | Admin deletion must never delete education content.
            | Therefore admin foreign keys are nullable and use nullOnDelete().
            |
            */

            $table->foreignId('created_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->foreignId('updated_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->timestamps();

            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | Query Indexes
            |--------------------------------------------------------------------------
            */

            $table->index([
                'status',
                'sort_order',
            ]);

            $table->index([
                'access_level',
                'status',
            ]);

            $table->index([
                'language_code',
                'status',
            ]);

            $table->index([
                'is_featured',
                'status',
            ]);

            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
