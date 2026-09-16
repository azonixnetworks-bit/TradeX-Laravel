<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('economic_news_sources', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 60)->unique();
            $table->string('name', 160);
            $table->string('source_type', 30)->default('MANUAL');
            $table->string('homepage_url', 500)->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->decimal('reliability_weight', 5, 2)->default(100.00);
            $table->json('public_config_json')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_fetched_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->string('last_error_code', 120)->nullable();
            $table->text('last_error_message')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_enabled', 'priority'], 'economic_news_sources_enabled_priority_idx');
        });

        Schema::create('economic_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('economic_news_source_id')->nullable()->constrained('economic_news_sources')->nullOnDelete();
            $table->string('provider_event_id', 191)->nullable();
            $table->string('origin', 20)->default('MANUAL');
            $table->string('title', 255);
            $table->string('category', 100)->nullable();
            $table->string('country_code', 8)->nullable();
            $table->string('currency', 12)->nullable();
            $table->string('impact', 16)->default('MEDIUM');
            $table->timestamp('scheduled_at_utc');
            $table->string('source_timezone', 64)->default('UTC');
            $table->string('status', 20)->default('SCHEDULED');
            $table->string('actual', 120)->nullable();
            $table->string('forecast', 120)->nullable();
            $table->string('previous', 120)->nullable();
            $table->string('revised_previous', 120)->nullable();
            $table->string('bias', 16)->default('NEUTRAL');
            $table->unsignedTinyInteger('confidence_score')->default(0);
            $table->string('volatility', 16)->default('MEDIUM');
            $table->text('reason')->nullable();
            $table->longText('analysis')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('source_payload_json')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['economic_news_source_id', 'provider_event_id'],
                'economic_events_source_provider_event_unique'
            );
            $table->index(['scheduled_at_utc', 'impact'], 'economic_events_schedule_impact_idx');
            $table->index(['is_published', 'scheduled_at_utc'], 'economic_events_published_schedule_idx');
            $table->index(['status', 'scheduled_at_utc'], 'economic_events_status_schedule_idx');
            $table->index(['currency', 'scheduled_at_utc'], 'economic_events_currency_schedule_idx');
        });

        Schema::create('economic_event_pairs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('economic_event_id')->constrained('economic_events')->cascadeOnDelete();
            $table->string('market', 20)->default('FOREX');
            $table->string('symbol', 50);
            $table->string('bias', 16)->default('NEUTRAL');
            $table->unsignedTinyInteger('confidence_score')->default(0);
            $table->string('volatility', 16)->default('MEDIUM');
            $table->text('reason')->nullable();
            $table->string('mapping_source', 20)->default('MANUAL');
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();

            $table->unique(['economic_event_id', 'market', 'symbol'], 'economic_event_pairs_event_market_symbol_unique');
            $table->index(['market', 'symbol'], 'economic_event_pairs_market_symbol_idx');
            $table->index(['economic_event_id', 'sort_order'], 'economic_event_pairs_event_sort_idx');
        });

        Schema::create('economic_event_updates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('economic_event_id')->constrained('economic_events')->cascadeOnDelete();
            $table->foreignId('economic_news_source_id')->nullable()->constrained('economic_news_sources')->nullOnDelete();
            $table->string('provider_update_id', 191)->nullable();
            $table->string('update_type', 30)->default('LIVE');
            $table->text('message');
            $table->string('actual', 120)->nullable();
            $table->string('forecast', 120)->nullable();
            $table->string('previous', 120)->nullable();
            $table->string('bias', 16)->nullable();
            $table->unsignedTinyInteger('confidence_score')->nullable();
            $table->string('volatility', 16)->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamp('published_at');
            $table->json('source_payload_json')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['economic_news_source_id', 'provider_update_id'],
                'economic_event_updates_source_provider_unique'
            );
            $table->index(['economic_event_id', 'published_at'], 'economic_event_updates_event_published_idx');
            $table->index(['is_published', 'published_at'], 'economic_event_updates_published_idx');
        });

        Schema::create('economic_event_overrides', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('economic_event_id')->constrained('economic_events')->cascadeOnDelete();
            $table->string('impact', 16)->nullable();
            $table->string('status', 20)->nullable();
            $table->string('bias', 16)->nullable();
            $table->unsignedTinyInteger('confidence_score')->nullable();
            $table->string('volatility', 16)->nullable();
            $table->text('reason')->nullable();
            $table->longText('analysis')->nullable();
            $table->text('override_note');
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('overridden_by_admin_id')->constrained('admin_users')->restrictOnDelete();
            $table->foreignId('revoked_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();

            $table->index(['economic_event_id', 'is_active'], 'economic_event_overrides_event_active_idx');
            $table->index(['is_active', 'expires_at'], 'economic_event_overrides_active_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('economic_event_overrides');
        Schema::dropIfExists('economic_event_updates');
        Schema::dropIfExists('economic_event_pairs');
        Schema::dropIfExists('economic_events');
        Schema::dropIfExists('economic_news_sources');
    }
};
