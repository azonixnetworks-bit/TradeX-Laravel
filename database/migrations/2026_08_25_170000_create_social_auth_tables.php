<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_social_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('provider_user_id', 255);
            $table->string('provider_email', 255)->nullable();
            $table->timestamp('linked_at');
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id'], 'user_social_provider_subject_unique');
            $table->unique(['user_id', 'provider'], 'user_social_user_provider_unique');
        });

        Schema::create('user_social_auth_challenges', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('provider', 20);
            $table->string('purpose', 30);
            $table->char('nonce_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('request_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['provider', 'purpose', 'expires_at'], 'social_challenge_lookup_idx');
        });

        Schema::table('user_registration_sessions', function (Blueprint $table): void {
            $table->string('social_provider', 20)->nullable()->after('language_code');
            $table->string('social_provider_user_id', 255)->nullable()->after('social_provider');
            $table->string('social_provider_email', 255)->nullable()->after('social_provider_user_id');
            $table->index(
                ['social_provider', 'social_provider_user_id'],
                'registration_social_identity_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('user_registration_sessions', function (Blueprint $table): void {
            $table->dropIndex('registration_social_identity_idx');
            $table->dropColumn([
                'social_provider',
                'social_provider_user_id',
                'social_provider_email',
            ]);
        });

        Schema::dropIfExists('user_social_auth_challenges');
        Schema::dropIfExists('user_social_accounts');
    }
};
