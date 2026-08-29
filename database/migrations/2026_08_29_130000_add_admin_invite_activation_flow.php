<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'user_registration_sessions',
            function (Blueprint $table): void {
                $table->string('source', 20)
                    ->default('SELF_REGISTER')
                    ->after('language_code')
                    ->index();

                $table->foreignId(
                    'customer_onboarding_invitation_id'
                )
                    ->nullable()
                    ->after('source')
                    ->constrained(
                        'customer_onboarding_invitations'
                    )
                    ->nullOnDelete();

                $table->timestamp('password_set_at')
                    ->nullable()
                    ->after('whatsapp_verified_at');

                $table->index(
                    [
                        'source',
                        'status',
                    ],
                    'registration_source_status_idx'
                );
            }
        );

        Schema::table(
            'customer_onboarding_invitations',
            function (Blueprint $table): void {
                $table->timestamp(
                    'activation_consumed_at'
                )
                    ->nullable()
                    ->after('last_invited_at');
            }
        );

        Schema::create(
            'onboarding_legal_policy_acceptances',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->foreignId(
                    'user_registration_session_id'
                )
                    ->constrained(
                        'user_registration_sessions'
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'legal_policy_version_id'
                )
                    ->constrained(
                        'legal_policy_versions'
                    )
                    ->restrictOnDelete();

                $table->string('policy_type', 60);
                $table->unsignedInteger(
                    'policy_version_number'
                );
                $table->char('content_sha256', 64);
                $table->timestamp('accepted_at');
                $table->string(
                    'accepted_ip',
                    45
                )->nullable();
                $table->string(
                    'accepted_user_agent',
                    1024
                )->nullable();
                $table->timestamps();

                $table->unique(
                    [
                        'user_registration_session_id',
                        'legal_policy_version_id',
                    ],
                    'onboarding_legal_session_version_uq'
                );

                $table->index(
                    [
                        'user_registration_session_id',
                        'policy_type',
                    ],
                    'onboarding_legal_session_type_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'onboarding_legal_policy_acceptances'
        );

        Schema::table(
            'customer_onboarding_invitations',
            function (Blueprint $table): void {
                $table->dropColumn(
                    'activation_consumed_at'
                );
            }
        );

        Schema::table(
            'user_registration_sessions',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'registration_source_status_idx'
                );

                $table->dropConstrainedForeignId(
                    'customer_onboarding_invitation_id'
                );

                $table->dropColumn([
                    'source',
                    'password_set_at',
                ]);
            }
        );
    }
};
