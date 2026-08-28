<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copy_trade_brokers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 40)->unique();
            $table->string('name', 120);

            $table->string('logo_disk', 40)->nullable();
            $table->string('logo_path', 500)->nullable();
            $table->string('logo_mime_type', 100)->nullable();
            $table->unsignedBigInteger('logo_size_bytes')->nullable();

            $table->boolean('is_active')->default(true)->index();

            $table->foreignId('created_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->foreignId('updated_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->timestamps();
        });

        Schema::create('copy_trade_broker_markets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('broker_id')
                ->constrained('copy_trade_brokers')
                ->restrictOnDelete();

            $table->string('market_category', 20)->index();
            $table->string('referral_url', 2048);
            $table->string('required_currency', 10)->default('USD');
            $table->boolean('live_account_only')->default(true);
            $table->boolean('is_active')->default(true)->index();

            $table->foreignId('created_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->foreignId('updated_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['broker_id', 'market_category'],
                'copy_trade_broker_market_unique'
            );
        });

        Schema::create('copy_trade_onboarding_intents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('broker_market_id')
                ->constrained('copy_trade_broker_markets')
                ->restrictOnDelete();

            $table->string('referral_url_snapshot', 2048);
            $table->timestamp('opened_at')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(
                ['user_id', 'broker_market_id', 'opened_at'],
                'copy_trade_onboarding_user_market_idx'
            );
        });

        Schema::create('copy_trade_broker_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('broker_id')
                ->constrained('copy_trade_brokers')
                ->restrictOnDelete();

            $table->foreignId('broker_market_id')
                ->constrained('copy_trade_broker_markets')
                ->restrictOnDelete();

            $table->foreignId('onboarding_intent_id')
                ->nullable()
                ->constrained('copy_trade_onboarding_intents')
                ->nullOnDelete();

            /*
            | Real account numbers are encrypted using Laravel's encrypted cast.
            | Fingerprint is deterministic for duplicate detection only.
            */
            $table->text('account_number');
            $table->char('account_number_fingerprint', 64)->unique();
            $table->string('account_number_last4', 4);

            $table->string('status', 30)->default('PENDING')->index();
            $table->unsignedTinyInteger('resubmission_count')->default(0);

            $table->string('verified_currency', 10)->nullable();
            $table->string('verified_account_mode', 20)->nullable();
            $table->timestamp('partner_attribution_confirmed_at')->nullable();

            $table->text('last_status_reason')->nullable();

            $table->timestamp('submitted_at')->index();
            $table->timestamp('verified_at')->nullable()->index();
            $table->timestamp('activated_at')->nullable()->index();
            $table->timestamp('suspended_at')->nullable()->index();
            $table->timestamp('disconnected_at')->nullable()->index();
            $table->timestamp('rejected_at')->nullable()->index();
            $table->timestamp('escalated_at')->nullable()->index();

            $table->foreignId('verified_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->foreignId('status_changed_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(
                ['user_id', 'status'],
                'copy_trade_account_user_status_idx'
            );

            $table->index(
                ['user_id', 'broker_market_id'],
                'copy_trade_account_user_market_idx'
            );
        });

        Schema::create('copy_trade_broker_account_reviews', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('broker_account_id')
                ->constrained('copy_trade_broker_accounts')
                ->restrictOnDelete();

            $table->foreignId('admin_user_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->string('action', 40)->index();
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30)->nullable();

            $table->text('reason')->nullable();
            $table->boolean('partner_attribution_confirmed')->nullable();
            $table->boolean('live_account_confirmed')->nullable();
            $table->string('verified_currency', 10)->nullable();
            $table->text('portal_reference')->nullable();

            $table->string('evidence_disk', 40)->nullable();
            $table->string('evidence_path', 500)->nullable();
            $table->string('evidence_mime_type', 100)->nullable();
            $table->unsignedBigInteger('evidence_size_bytes')->nullable();

            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copy_trade_broker_account_reviews');
        Schema::dropIfExists('copy_trade_broker_accounts');
        Schema::dropIfExists('copy_trade_onboarding_intents');
        Schema::dropIfExists('copy_trade_broker_markets');
        Schema::dropIfExists('copy_trade_brokers');
    }
};
